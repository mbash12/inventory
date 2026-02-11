<script setup>
// HIDE PO DEPOSIT NOTIFICATION FOR DELIVERY
import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import { reactive, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
const router = useRouter();
import {
    currentUser,
    getNotificationCount,
    getNotificationList,
    readNotification,
    logout,
    goto
} from "../services/service";
import { loading } from "../services/router";
dayjs.extend(relativeTime);
const notiffilter = ["all", "marketing", "finance", "delivery"];
const user = currentUser.user?.user;
const route = useRoute();
const currentUrl = route.path;
const state = reactive({
    current_notif: "all",
    menu_open: true,
    notif_open: false,
    page: 1,
    notification_count: null,
    notifications: [],
    notifications_meta: {},
    submenuOpen: {},  // Add this new property
    manuallyClosedMenus: new Set(), // Add this new property
});
const setnotiffilter = (name) => {
    loading()
    state.current_notif = name;
    getNotif()
};
const toggle_menu = () => {
    state.menu_open = !state.menu_open;
    localStorage.setItem("menu_open", state.menu_open);
};
const toggle_notif = () => {
    state.notif_open = !state.notif_open;
    if (state.notif_open) {
        getNotif();
    }
};
const setReadNotif = (id = "all", data = null) => {
    readNotification(id).then(() => getNotif(), getNotifCount());
    state.notif_open = false;
    let payload = JSON.parse(data.payload)
    const navigationRoutes = {
        delivery: {
            project: "/details/",
            deposit: "/not-authorized/",
            delivery: "/details/",
            threads: "/threads/",
        },
        marketing: {
            project: "/projects/edit/",
            deposit: "/podeposits/edit/",
            delivery: "/delivery-report/logs/",
            threads: "/threads/",
        },
        finance: {
            project: "/projects/edit/",
            deposit: "/not-authorized/",
            delivery: "/delivery-report/logs/",
            threads: "/threads/",
        },
    };
    const userPositionRoutes = navigationRoutes[user.position] || {};
    const routeBase = userPositionRoutes[data.type];
    const projectId = payload?.project?.id;
    const depositId = payload?.po_deposit?.id;
    if (routeBase) {
        const routeEnd = data.type === "deposit" ? depositId : projectId;
        const isDesktop = route.path.includes("desktop");
        goto(`${isDesktop ? "/desktop" : ""}${routeBase}${routeEnd}`);
    }
    
};
const getNotif = () => {
    state.page = 1;
    getNotificationList({ role: state.current_notif }).then((r) => {
        state.notifications = r.data;
        state.notifications_meta = r.meta;
        loading(false)
    });
};
const getMoreNotif = () => {
    state.page++;
    getNotificationList({ page: state.page, role: state.current_notif }).then(
        (r) => {
            state.notifications = [...state.notifications, ...r.data];
            state.notifications_meta = r.meta;
        }
    );
};
const getNotifCount = () => {
    getNotificationCount().then((r) => {
        state.notification_count = r.data;
    });
};
const doLogout = () => {
    logout();
    router.push("/desktop/login");
};
const toggleSubmenu = (menuIndex) => {
    if (state.submenuOpen[menuIndex]) {
        state.manuallyClosedMenus.add(menuIndex);
    } else {
        state.manuallyClosedMenus.delete(menuIndex);
    }
    state.submenuOpen[menuIndex] = !state.submenuOpen[menuIndex];
};
const isSubmenuActive = (menu) => {
    if (!menu.submenu) return false;
    return menu.submenu.some(submenu => currentUrl.includes(submenu.activeon));
};
const shouldShowSubmenu = (menu, index) => {
    const isActive = isSubmenuActive(menu);
    // Show if active and not manually closed, or if manually opened
    return (isActive && !state.manuallyClosedMenus.has(index)) || state.submenuOpen[index];
};

onMounted(() => {
    let opened = localStorage.getItem("menu_open");
    state.menu_open = opened === null ? true : opened === "true";
    
    // Initialize submenu state based on active routes
    menus.forEach((menu, index) => {
        if (isSubmenuActive(menu)) {
            state.submenuOpen[index] = true;
        }
    });
    
    getNotifCount();
});
const menus = [
    {
        icon: "ri-speed-up-fill",
        title: "Dashboard",
        activeon: "desktopdashboard",
        link: "/desktop/dashboard",
        roles: ["admin", "finance"],
        
    },
    // {
    //     icon: "ri-calendar-schedule-fill",
    //     title: "Deadlines",
    //     activeon: "desktopdeadlines",
    //     link: "/desktop/deadlines",
    //     roles: ["marketing", "admin", "finance","delivery"],
    // },
    {
        icon: "ri-calendar-schedule-fill",
        title: "Project Todo",
        activeon: "desktopdeadlines",
        link: "",
        roles: ["marketing", "admin", "finance", "delivery","design"],
        submenu: [
            {
                title: "Task List",
                link: "/desktop/task-list",
                activeon: "desktop/task-list"
            },
            {
                title: "Calendar",
                link: "/desktop/calendar",
                activeon: "desktop/calendar"
            }
        ]
    },
    {
        icon: "ri-article-fill",
        title: "Projects",
        activeon: "desktopprojects",
        link: "",
        roles: ["marketing", "admin","delivery","design","finance"],
        submenu: [
            // {
            //     title: "All Projects",
            //     link: "/desktop",
            //     activeon: "desktop/projects"
            // },
            {
                title: "Gimmick",
                link: "/desktop/gimmick",
                activeon: "desktop/gimmick"
            },
            {
                title: "Design & Printing",
                link: "/desktop/design-printing",
                activeon: "desktop/design-printing"
            },
            {
                title: "Supplier Payment",
                link: "/desktop/supplier-payment",
                activeon: "desktop/supplier-payment"
            }
        ]
    },
    {
        icon: "ri-article-fill",
        title: "Finance Data",
        activeon: "financedata",
        link: "",
        roles: ["admin", "finance"],
        submenu: [
            {
                title: "Non Deposits",
                link: "/desktop/nondeposits",
                activeon: "desktop/nondeposit"
            },
            {
                title: "Deposits",
                link: "/desktop/deposits",
                activeon: "desktop/deposits"
            },
        ]
    },
    {
        icon: "ri-refresh-line",
        title: "Sync Monitoring",
        activeon: "desktopsync-monitoring",
        link: "/desktop/sync-monitoring",
        roles: ["admin", "finance"],
    },
    {
        icon: "ri-money-dollar-circle-fill",
        title: "PO Deposit",
        activeon: "desktoppodeposits",
        link: "/desktop/podeposits",
        roles: ["marketing", "admin", "finance", "delivery"],
    },
    {
        icon: "ri-box-2-fill",
        title: "Delivery Report",
        activeon: "desktopdelivery-report",
        link: "/desktop/delivery-report",
        roles: ["marketing", "admin", "finance", "delivery"],
    },
    {
        icon: "ri-hotel-fill",
        title: "Inventory",
        activeon: "desktopinventory",
        link: "/desktop/inventory",
        roles: ["marketing", "admin", "finance", "delivery"],
    },
    {
        icon: "ri-service-fill",
        title: "Clients",
        activeon: "desktopclients",
        link: "/desktop/clients",
        roles: ["marketing", "admin"],
    },

    {
        icon: "ri-group-fill",
        title: "Users",
        activeon: "desktopusers",
        link: "/desktop/users",
        roles: ["admin"],
    },
    {
        icon: "ri-store-3-fill",
        title: "Warehouses",
        activeon: "desktopwarehouses",
        link: "/desktop/warehouses",
        roles: ["admin", "delivery"],
    },
    {
        icon: "ri-truck-fill",
        title: "Shipping Vendors",
        activeon: "desktopshipping-vendors",
        link: "/desktop/shipping-vendors",
        roles: ["admin", "delivery"],
    },
];
</script>
<template>
    <div class="flex fixed top-0 left-0 w-full h-full mkt text-[#001737]">
        <div
            class="flex-1 transition-all pt-55px bg-[#F0F1F6] overflow-auto"
            :class="state.menu_open ? 'ml-280px' : 'ml-0'"
        >
            <slot :layoutData="{menu_open: state.menu_open}"></slot>
        </div>
        <div class="h-55px w-full bg-[#df3b3e] flex fixed top-0 left-0 z-20">
            <div
                class="w-280px h-full border-r border-[#C83131] flex items-center justify-center"
            >
                <img src="../assets/logo-white.svg" alt="" class="h-35px" />
            </div>
            <div class="flex-1 h-full">
                <button
                    type="button"
                    class="h-full text-2xl bg-[#df3b3e] text-white filter hover:bg-[#cc3538] flex items-center justify-center"
                    style="aspect-ratio: 1/1"
                    @click="toggle_menu"
                >
                    <i class="ri-menu-line"></i>
                </button>
            </div>
            <div class="flex h-full items-center pr-16">
                <div class="relative w-55px" style="aspect-ratio: 1/1">
                    <button
                        type="button"
                        class="h-full text-2xl bg-[#df3b3e] text-white filter hover:bg-[#cc3538] flex items-center justify-center relative"
                        style="aspect-ratio: 1/1"
                        @click="toggle_notif"
                    >
                        <i class="ri-notification-4-line"></i>
                        <span
                            v-show="state.notification_count?.all > 0"
                            class="absolute w-7 h-7 text-10px rounded-full bg-white text-black flex items-center justify-center font-semibold top-0 right-0 border-4 border-[#df3b3e]"
                            >{{
                                state.notification_count?.all > 99
                                    ? "99"
                                    : state.notification_count?.all
                            }}</span
                        >
                    </button>

                    <div
                        class="fixed top-0 left-0 w-full h-full bg-[#00000011] z-1"
                        @click="() => (state.notif_open = false)"
                        :class="state.notif_open ? 'block' : 'hidden'"
                    ></div>
                    <div
                        class="absolute z-1 mt-2 transition-all transform origin-top-right right-0"
                        :class="
                            state.notif_open
                                ? 'max-h-400px opacity-100 scale-100'
                                : 'max-h-0 opacity-50 scale-10 overflow-hidden'
                        "
                    >
                        <div
                            class="w-400px bg-white rounded-lg shadow-2xl border h-480px flex flex-col overflow-hidden"
                        >
                            <div
                                class="flex py-4 px-6 justify-between items-center"
                            >
                                <strong class="text-lg">Notifications</strong>
                                <div
                                    class="text-red-500 text-xs font-normal cursor-pointer"
                                    @click="setReadNotif()"
                                >
                                    Mark all as read
                                </div>
                            </div>
                            <div class="px-6 border-b flex gap-3">
                                <template
                                    v-for="(item, i) in notiffilter"
                                    :key="i"
                                >
                                    <div
                                        class="py-3 border-b-2 px-1 flex gap-1 items-center -mb-1px cursor-pointer"
                                        :class="
                                            state.current_notif == item
                                                ? 'border-[#DF3737]'
                                                : 'border-transparent'
                                        "
                                        @click="() => setnotiffilter(item)"
                                    >
                                        <span
                                            class="text-12px font-inter capitalize"
                                            :class="
                                                state.current_notif == item
                                                    ? 'text-[#DF3737] font-bold'
                                                    : 'text-[#667085]'
                                            "
                                            >{{ item }}</span
                                        >
                                        <span
                                            class="text-10px w-18px h-18px rounded-full flex items-center justify-center -mt-2px font-inter"
                                            :class="
                                                state.current_notif == item
                                                    ? 'bg-[#FFD0D0] text-[#DF3737] font-bold'
                                                    : 'bg-[#DFE1E7] text-[#667085]'
                                            "
                                            >{{
                                                state.notification_count?.[
                                                    item
                                                ] > 99
                                                    ? "99"
                                                    : state
                                                          .notification_count?.[
                                                          item
                                                      ]
                                            }}</span
                                        >
                                    </div>
                                </template>
                            </div>
                            <div
                                class="flex-1 overflow-y-scroll"
                                v-if="state.notifications.length"
                            >
                                <div
                                    v-for="(notif, i) in state.notifications"
                                    :key="i"
                                    class="w-full px-4 py-3 flex gap-4 items-center relative cursor-pointer"
                                    :class="
                                        notif.readed_at === null
                                            ? 'bg-[#FF8F8F0D]'
                                            : ''
                                    "
                                    @click="
                                        () => {
                                            setReadNotif(notif.id, notif);
                                            // router.push(notif.meta.url);
                                        }
                                    "
                                >
                                    <div
                                        class="w-45px h-45px rounded-full text-red-500 bg-[#FFCFCFB2] flex items-center justify-center flex-shrink-0 text-xl"
                                    >
                                        <i class="ri-notification-4-line"></i>
                                    </div>
                                    <div
                                        class="flex flex-col justify-center text-left gap-3px"
                                    >
                                        <div
                                            class="text-10px text-gray-400 text-light flex items-center gap-2"
                                        >
                                            <span class="capitalize">{{
                                                notif.type ?? "project"
                                            }}</span>
                                            <span
                                                class="w-2px h-2px inline-block rounded bg-gray-500"
                                            ></span>
                                            <span>{{
                                                dayjs().to(
                                                    dayjs(notif.created_at)
                                                )
                                            }}</span>
                                        </div>
                                        <p class="text-xs font-semibold">
                                            {{ notif.title }}
                                        </p>
                                        <span
                                            class="text-10px text-gray-500 font-light leading-4 w-full block"
                                            >{{ notif.content }}</span
                                        >
                                    </div>
                                    <div
                                        class="absolute w-[92%] h-1px bg-gray-200 bottom-0"
                                    ></div>
                                </div>
                                <div
                                    @click="getMoreNotif"
                                    class="flex items-center justify-center text-14px py-3 cursor-pointer"
                                    v-show="
                                        state.page <
                                        state.notifications_meta?.total_pages
                                    "
                                >
                                    Load more
                                </div>
                            </div>

                            <div
                                class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                                v-else
                            >
                                <i
                                    class="ri-inbox-line text-100px text-gray-200"
                                ></i>
                                <span class="text-sm font-medium text-gray-400"
                                    >No Notifications Available</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="fixed top-55px transition-all w-280px h-[calc(100%-55px)] border-r flex flex-col min-h-0"
            :class="state.menu_open ? 'left-0' : '-left-280px'"
        >
            <div class="px-4 border-b w-full h-100px flex items-center">
                <div class="flex gap-4 items-center">
                    <!-- <div class="w-50px h-50px rounded-full bg-black"></div> -->
                    <div class="flex flex-col gap-1">
                        <div class="text-lg text-[#001737] font-medium">
                            {{ user.name }}
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-500 capitalize">{{
                                user.position
                            }}</span>
                        </div>
                        <!-- <div class="flex items-center gap-1">
                            <div
                                class="w-2 h-2 bg-[#24FF00] rounded-full"
                            ></div>
                            <span class="text-xs text-black">online</span>
                        </div> -->
                    </div>
                </div>
            </div>
            <div class="flex-1 w-full overflow-auto">
                <div class="flex flex-col gap-2 py-4">
                    <template v-for="(menu, i) in menus" :key="i">
                        <div v-if="menu.roles.includes(user.position)">
                            <router-link
                                v-if="!menu.submenu"
                                :to="menu.link"
                            >
                                <div class="flex pl-2 pr-4 relative group">
                                    <div
                                        class="flex flex-1 p-2 items-center gap-2 rounded-md transition-all"
                                        :class="currentUrl.replace(/\//g, '').includes(menu.activeon) ? 'bg-red-100' : 'bg-white'"
                                    >
                                        <div class="flex items-center justify-center p-2 bg-red-500 rounded-lg w-35px h-35px text-white text-lg">
                                            <i :class="menu.icon"></i>
                                        </div>
                                        <div :class="currentUrl.replace(/\//g, '').includes(menu.activeon) ? 'text-red-500 font-bold' : 'group-hover:text-red-500 text-[#404047]'">
                                            {{ menu.title }}
                                        </div>
                                    </div>
                                    <div class="absolute h-full w-6px bg-red-500 right-0 rounded-l"
                                        :class="currentUrl.replace(/\//g, '').includes(menu.activeon) ? 'block' : 'hidden'"
                                    ></div>
                                </div>
                            </router-link>
                            <div v-else>
                                <div 
                                    class="flex pl-2 pr-4 relative group cursor-pointer" 
                                    @click="toggleSubmenu(i)"
                                >
                                    <div class="flex flex-1 p-2 items-center gap-2 rounded-md transition-all"
                                        :class="currentUrl.replace(/\//g, '').includes(menu.activeon) ? 'bg-red-100' : 'bg-white'"
                                    >
                                        <div class="flex items-center justify-center p-2 bg-red-500 rounded-lg w-35px h-35px text-white text-lg">
                                            <i :class="menu.icon"></i>
                                        </div>
                                        <div class="flex items-center justify-between flex-1">
                                            <div :class="currentUrl.replace(/\//g, '').includes(menu.activeon) ? 'text-red-500 font-bold' : 'group-hover:text-red-500 text-[#404047]'">
                                                {{ menu.title }}
                                            </div>
                                            <i 
                                                class="ri-arrow-down-s-line transform transition-transform duration-200"
                                                :class="state.submenuOpen[i] ? 'rotate-180' : ''"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                                <div 
                                    class="ml-4 overflow-hidden transition-all duration-200 flex flex-col "
                                    :class="shouldShowSubmenu(menu, i) ? 'max-h-[500px] opacity-100 my-3' : 'max-h-0 opacity-0'"
                                >
                                    <router-link
                                        v-for="(submenu, j) in menu.submenu"
                                        :key="j"
                                        :to="submenu.link"
                                        class="flex items-center gap-4 py-2 px-4 text-sm hover:text-red-500"
                                        :class="currentUrl.includes(submenu.activeon) ? 'text-red-500 font-bold' : 'text-[#404047]'"
                                    >
                                        <span class="w-6px h-6px inline-block rounded" :class="currentUrl.includes(submenu.activeon) ? 'bg-red-500' : 'bg-[#404047]'"></span>
                                        <span>
                                            {{ submenu.title }}
                                        </span>
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div>
                <button
                    class="h-60px w-full flex items-center justify-start p-6 gap-3 text-[#8E94A9] font-medium bg-white hover:bg-gray-50 hover:text-[#df3b3e]"
                    @click="doLogout"
                >
                    <i class="ri-logout-box-r-line text-lg font-medium"></i>
                    <span class="block flex-1 text-left">Log Out</span>
                </button>
            </div>
        </div>
    </div>
</template>
