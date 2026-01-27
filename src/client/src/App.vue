<script setup>
import { onMounted, ref, reactive } from "vue";
import Loading from "vue-loading-overlay";
import "vue-loading-overlay/dist/vue-loading.css";
import Splash from "./pages/Splash.vue";
import { store } from "./services/router";
import {
    checkLoggedin,
    api,
    setToken,
    currentUser,
    goto,
} from "./services/service";
import { getTokens, onMessageListener } from "./services/firebase";
import dayjs from "dayjs";
import { useRoute } from "vue-router";
const inAppNotif = ref(null);
const route = useRoute();
const user = reactive({});
let loggedin = ref(null);
const state = reactive({
    notifications: [],
});
const init = async (token = null) => {
    setTimeout(() => {
        checkLoggedin()
            .then(async (status) => {
                if (status) {
                    loggedin.value = true;
                    if (token) {
                        setToken({ token: token });
                        user.value = currentUser.user?.user;
                    }
                } else {
                    loggedin.value = false;
                }
            })
            .catch(() => {
                loggedin.value = false;
            });
    }, 500);
};
const displayNotif = (payload) => {
    const data = JSON.parse(payload?.data?.payload);
    let thenotif = document.createElement("div");
    const removeTheNotif = () => {
        thenotif.style.transform = "scale(0)";
        thenotif.style.opacity = "0";
        setTimeout(() => {
            thenotif.remove();
        }, 300);
    };
    thenotif.setAttribute(
        "class",
        "w-full shadow-lg rounded-lg flex bg-white transition-all duration-300 relative"
    );
    thenotif.style.cssText = `transform: scale(0); opacity: 0;`;
    thenotif.innerHTML = `
                    <div
                        class="w-full px-4 py-3 flex gap-4 items-center relative cursor-pointer"
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
                                <span class="capitalize">${data?.type}</span>
                                <span
                                    class="w-2px h-2px inline-block rounded bg-gray-500"
                                ></span>
                                <span></span>
                            </div>
                            <p class="text-xs font-semibold">
                                ${data?.title}
                            </p>
                            <span
                                class="text-10px text-gray-500 font-light leading-4 w-full block"
                                >${data?.content}</span
                            >
                        </div>
                    </div>
            `;
    let closeButton = document.createElement("div");
    closeButton.setAttribute(
        "class",
        "absolute shadow-lg -right-2 -top-2 w-6 h-6 rounded-full bg-gray-400 flex items-center cursor-pointer justify-center text-white"
    );
    closeButton.innerHTML = `       
                <i class="ri-close-line"></i>
            `;
    closeButton.addEventListener("click", removeTheNotif);
    thenotif.appendChild(closeButton);
    thenotif.addEventListener("click", () =>
        setReadNotif(data, removeTheNotif)
    );
    inAppNotif.value.prepend(thenotif);
    setTimeout(() => {
        thenotif.style.cssText = `transform: scale(1); opacity: 1;`;
    }, 10);
    setTimeout(() => {
        removeTheNotif();
    }, 10000);
};
const setReadNotif = (data = null, callback) => {
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
    let payload = JSON.parse(data.payload);
    const userPositionRoutes = navigationRoutes[user.value.position] || {};
    const routeBase = userPositionRoutes[data.type];
    const projectId = payload?.project?.id;
    const depositId = payload?.po_deposit?.id;
    callback();
    if (routeBase) {
        const routeEnd = data.type === "deposit" ? depositId : projectId;
        const isDesktop = route.path.includes("desktop");
        goto(`${isDesktop ? "/desktop" : ""}${routeBase}${routeEnd}`);
    }
};
onMounted(async () => {
    let result = await getTokens();
    if (result) {
        onMessageListener(displayNotif);
        init(result);
    } else {
        init();
    }
});
</script>
<template>
    <div class="w-full h-full fixed top-0 left-0 bg-[#FEFEFE]">
        <template v-if="loggedin === null">
            <div class="transition w-full h-full">
                <Splash></Splash>
            </div>
        </template>
        <template v-else>
            <RouterView v-slot="{ Component, route }">
                <!-- <Transition
                    name="fade"
                    :css="route.path.includes('desktop') ? false : true"
                > -->
                <div :key="route.name" class="page w-full h-full">
                    <KeepAlive>
                        <component :is="Component"></component>
                    </KeepAlive>
                </div>
                <!-- </Transition> -->
            </RouterView>
        </template>
    </div>

    <div
        class="fixed lg:w-400px w-full h-auto top-14 right-0 flex flex-col gap-2 z-10"
        ref="inAppNotif"
    >
        <!-- <div class="w-full shadow-lg rounded-lg flex bg-white" >
            <div
                class="w-full px-4 py-3 flex gap-4 items-center relative cursor-pointer"
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
                        <span class="capitalize">project</span>
                        <span
                            class="w-2px h-2px inline-block rounded bg-gray-500"
                        ></span>
                        <span>Lorem ipsum dolor sit.</span>
                    </div>
                    <p class="text-xs font-semibold">
                        Lorem ipsum dolor sit amet.
                    </p>
                    <span
                        class="text-10px text-gray-500 font-light leading-4 w-full block"
                        >Lorem ipsum dolor, sit amet consectetur
                        adipisicing elit. Aperiam, perspiciatis!</span
                    >
                </div>
                <div class="absolute  shadow-lg -right-2 -top-2 w-6 h-6 rounded-full bg-gray-400 flex items-center justify-center text-white">
                    <i class="ri-close-line"></i>
                </div>
            </div>
        </div> -->
    </div>
    <loading
        v-model:active="store.isLoading"
        :can-cancel="false"
        :is-full-page="true"
        :color="'#ff0000'"
    />
</template>

<style>
.page {
    transition: all 0.3s ease;
}
/* .fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
} */
/* .fade-enter,
.fade-leave-to {
  opacity: 0;
} */

/* .fade-enter-active,
.fade-leave-active {
  transition: all 0.5s ease;
} */
.fade-enter-from,
.fade-leave-from,
.fade-enter-to,
.fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
/* .scale-enter-from,
.scale-leave-from,
.scale-enter-to,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
} */
</style>
