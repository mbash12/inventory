<script setup>
import { reactive, computed, onMounted } from "vue";
import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import {
    currentUser,
    getNotificationCount,
    getNotificationList,
    readNotification,
    logout,
    goto
} from "../services/service";
dayjs.extend(relativeTime);
const notiffilter = ["all", "marketing", "finance", "delivery"];
const user = currentUser.user?.user;
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    current_notif: "all",
    menu_open: false,
    notif_open: false,
    page: 1,
    notification_count: null,
    notifications: [],
    notifications_meta: {},
});

const setnotiffilter = (name) => {
    loading();
    state.current_notif = name;
    getNotif();
};
const toggle_menu = () => {
    state.menu_open = !state.menu_open;
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
        loading(false);
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
    router.push("/login");
};
onMounted(() => {
    // clearInterval(getNotifCount);
    // setInterval(getNotifCount, 60000);
    getNotifCount();
    // getNotif();
});
const menus = [
    // {
    //     icon: "ri-article-fill",
    //     title: "Projects",
    //     activeon: "projects",
    //     link: "/projects",
    //     roles: ["marketing", "admin", "finance","delivery"],
    // },
    {
        icon: "ri-article-fill",
        title: "Delivery",
        activeon: "deliveries",
        link: "/deliveries",
        roles: ["admin", "delivery"],
    },
    {
        icon: "ri-money-dollar-circle-fill",
        title: "PO Deposit",
        activeon: "podeposits",
        link: "/podeposits",
        roles: ["marketing", "admin","delivery","finance"],
    },
    {
        icon: "ri-box-2-fill",
        title: "Delivery Report",
        activeon: "delivery-report",
        link: "/delivery-report",
        roles: ["marketing", "admin", "finance", "delivery"],
    },
    {
        icon: "ri-hotel-fill",
        title: "Inventories",
        activeon: "inventories",
        link: "/inventories",
        roles: ["marketing", "admin", "finance", "delivery"],
    },
    {
        icon: "ri-service-fill",
        title: "Client",
        activeon: "clients",
        link: "/clients",
        roles: ["marketing", "admin"],
    },

    // {
    //     icon: "ri-group-fill",
    //     title: "Users",
    //     activeon: "users",
    //     link: "/users",
    //     roles: ["admin"],
    // },
    {
        icon: "ri-store-3-fill",
        title: "Warehouses",
        activeon: "warehouses",
        link: "/warehouses",
        roles: ["admin", "delivery"],
    },
    {
        icon: "ri-truck-fill",
        title: "Shipping Vendors",
        activeon: "shipping-vendors",
        link: "/shipping-vendors",
        roles: ["admin", "delivery"],
    },
];
</script>

<template>
    <main class="flex flex-col h-full">
        <div
            class="flex h-50px items-center py-4 px-2 gap-1 bg-[#df3b3e] text-white z-20"
        >
            <div
                class="reset w-10 h-10 rounded flex items-center justify-center text-lg"
                :class="state.menu_open ? 'bg-[#00000022]' : 'bg-[#00000000]'"
                @click="toggle_menu"
            >
                <i class="ri-menu-fill"></i>
            </div>
            <img
                src="../assets/logo_pelangi.png"
                alt=""
                class="h-6 filter grayscale-100 brightness-0 invert mr-1"
            />
            <strong>
                {{
                    menus?.find((e) =>
                        currentUrl?.replace(/\//g, "").includes(e.activeon)
                    )?.["title"]
                }}
            </strong>
            <div
                class="reset w-10 h-10 rounded flex items-center justify-center text-lg ml-auto relative"
                :class="state.notif_open ? 'bg-[#00000022]' : 'bg-[#00000000]'"
                @click="toggle_notif"
            >
                <i class="ri-notification-3-line"></i>
                <span class="absolute w-6 h-6 text-10px rounded-full bg-white text-black flex items-center justify-center font-semibold -top-1 -right-1 border-2 border-[#df3b3e]">
                    {{
                        state.notification_count?.all > 99
                            ? "99"
                            : state.notification_count?.all
                    }}
                </span>
            </div>
        </div>
        <div class="flex-1 min-h-0">
            <slot></slot>
        </div>

        <div
            class="fixed w-full h-full transition-all duration-500 z-10"
            :class="
                state.notif_open
                    ? 'bg-[#00000033] pointer-events-auto'
                    : 'bg-[#00000000] pointer-events-none'
            "
        >
            <div
                class="flex-1 w-full h-full overflow-auto bg-white flex flex-col absolute transition-all duration-300 pt-50px"
                :class="
                    state.notif_open
                        ? '-top-0  shadow-lg'
                        : '-top-full shadow-none'
                "
            >
                <div
                    class="bg-white rounded-lg flex flex-col overflow-hidden h-full"
                >
                    <div class="flex py-2 px-6 justify-between items-center">
                        <strong class="text-lg">Notifications</strong>
                        <div
                            class="text-red-500 text-xs font-normal cursor-pointer"
                            @click="setReadNotif()"
                        >
                            Mark all as read
                        </div>
                    </div>
                    <div class="px-6 border-b flex gap-3">
                        <template v-for="(item, i) in notiffilter" :key="i">
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
                                        state.notification_count?.[item] > 99
                                            ? "99"
                                            : state.notification_count?.[item]
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
                                notif.readed_at === null ? 'bg-[#FF8F8F0D]' : ''
                            "
                            @click="
                                () => {
                                    setReadNotif(notif.id, notif);
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
                                        dayjs().to(dayjs(notif.created_at))
                                    }}</span>
                                </div>
                                <p class="text-xs font-semibold">
                                    {{ notif.title }}
                                </p>
                                <span
                                    class="text-10px text-gray-500 font-light leading-3 w-full block"
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
                        <i class="ri-inbox-line text-100px text-gray-200"></i>
                        <span class="text-sm font-medium text-gray-400"
                            >No Notifications Available</span
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            class="fixed top-50px w-full h-[calc(100%-50px)] transition-all duration-300 z-10 flex"
            :class="
                state.menu_open ? 'pointer-events-auto' : 'pointer-events-none'
            "
        >
            <div
                class="flex-1 transition bg-[#00000033] backdrop-filter backdrop-blur-sm"
                @click="() => (state.menu_open = false)"
                :class="
                    state.menu_open
                        ? 'opacity-100'
                        : 'opacity-0'
                "
            ></div>
            <div
                class="flex-1 w-260px h-full overflow-auto bg-white flex flex-col absolute transition-all duration-300"
                :class="
                    state.menu_open
                        ? '-left-0  shadow-lg'
                        : '-left-260px shadow-none'
                "
            >
                <div class="flex flex-col gap-2 py-4">
                    <div class="flex flex-col gap-1 px-4 mb-2">
                        <div class="text-md text-[#001737] font-medium">
                            {{ user.name }}
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-500 capitalize">{{
                                user.position
                            }}</span>
                        </div>
                    </div>
                    <template v-for="(menu, i) in menus" :key="i">
                        <router-link
                            :to="menu.link"
                            v-if="menu.roles.includes(user.position)"
                        >
                            <div class="flex pl-2 pr-4 relative group">
                                <div
                                    class="flex flex-1 p-2 items-center gap-2 rounded-md transition-all"
                                    :class="
                                        currentUrl
                                            .replace(/\//g, '')
                                            .includes(menu.activeon)
                                            ? 'bg-red-100'
                                            : 'bg-white'
                                    "
                                >
                                    <div
                                        class="flex items-center justify-center p-2 bg-red-500 rounded-lg w-30px h-30px text-white text-lg"
                                    >
                                        <i :class="menu.icon"></i>
                                    </div>
                                    <div
                                        class="text-sm"
                                        :class="
                                            currentUrl
                                                .replace(/\//g, '')
                                                .includes(menu.activeon)
                                                ? 'text-red-500 font-bold'
                                                : 'group-hover:text-red-500 text-[#404047]'
                                        "
                                    >
                                        {{ menu.title }}
                                    </div>
                                </div>
                                <div
                                    class="absolute h-full w-6px bg-red-500 right-0 rounded-l"
                                    :class="
                                        currentUrl
                                            .replace(/\//g, '')
                                            .includes(menu.activeon)
                                            ? 'block'
                                            : 'hidden'
                                    "
                                ></div>
                            </div>
                        </router-link>
                    </template>
                </div>
                <div class="mt-auto">
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
    </main>
</template>
