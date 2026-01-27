<script setup>
import { useRouter } from "vue-router";
import { loading } from "../services/router";
import { currentUser, data, logout, isLoggedin, checkLoggedin } from "../services/service";
const props = defineProps({
  show: Boolean,
});
const router = useRouter();
const doLogout = async () => {
  loading();
  let result = await logout();
  let result1 = await checkLoggedin();
  loading(false);
  router.push("/login");
};
</script>

<template>
  <Transition name="drawer">
    <div class="w-full h-full fixed top-0 left-0 transition z-8" v-if="show">
      <div
        class="absolute w-full h-full bg-black bg-opacity-30 top-0 left-0 drawerbg transition"
      ></div>
      <div
        class="w-[300px] h-full bg-white absolute top-0 left-0 p-4 drawerct transition"
      >
        <div class="w-full h-full flex flex-col">
          <div class="flex border-b border-gray-200 px-2 py-8">
            <div class="w-[70px] h-[70px] rounded-full overflow-hidden">
              <img
                :src="currentUser.user.user.photos"
                class="w-full h-full object-cover"
                alt=""
              />
            </div>
            <div class="flex flex-col items-start justify-center gap-2 pl-4">
              <div class="text-app-600 font-bold text-[18px]">
                {{ currentUser.user.user.fullname }}
              </div>
              <div class="text-app-114 font-light text-[13px]">
                {{ currentUser.user.user.email }}
              </div>
            </div>
          </div>
          <div class="flex flex-col my-3 flex-1">
            <router-link to="/">
              <div
                class="flex items-center px-2 py-3 my-2 hover:bg-gray-100 rounded transition"
              >
                <img src="../assets/menu-grpo.png" alt="" />
                <div class="pl-3 text-app-600 font-bold text-[16px]">GRPO</div>
              </div>
            </router-link>
            <router-link to="/profile">
              <div
                class="flex items-center px-2 py-3 my-2 hover:bg-gray-100 rounded transition"
              >
                <img src="../assets/menu-profile.png" alt="" />
                <div class="pl-3 text-app-114 text-[16px]">Profile</div>
              </div>
            </router-link>
            <router-link to="/notifications">
              <div
                class="flex items-center px-2 py-3 my-2 hover:bg-gray-100 rounded transition"
              >
                <div class="relative">
                  <img src="../assets/menu-notification.png" alt="" />
                  <div
                    class="w-4 h-4 flex items-center justify-center rounded-full bg-app-800 text-white absolute -top-1 -right-1 font-bold text-[11px]"
                  >
                  {{ data.notifications }}
                  </div>
                </div>
                <div class="pl-3 text-app-114 text-[16px]">Notifications</div>
              </div>
            </router-link>
            <div class="flex-1 h-full"></div>
            <div
              class="flex items-center px-2 py-3 mb-12 hover:bg-gray-100 rounded transition cursor-pointer"
              @click="doLogout"
            >
              <img src="../assets/icon-logout.png" alt="" />
              <div class="pl-3 text-app-800 font-semibold text-[16px]">
                Logout
              </div>
            </div>
          </div>
        </div>
      </div>
      <div
        class="absolute left-[316px] top-4 drawercl transition cursor-pointer"
        @click="$emit('hide')"
      >
        <img src="../assets/icon-close-big.png" alt="" />
      </div>
    </div>
  </Transition>
</template>

<style>
/* .drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100vw);
} */

.drawer-enter-from .drawerbg,
.drawer-leave-to .drawerbg {
  opacity: 0;
}
.drawer-enter-from .drawerct,
.drawer-leave-to .drawerct,
.drawer-enter-from .drawercl,
.drawer-leave-to .drawercl {
  transform: translateX(-100vw);
}
</style>
