<script setup>
import { useRouter } from 'vue-router';
import { goback, goto } from '../services/service';
const props = defineProps({
    title: String,
    back: String,
    home: Boolean
});
const router = useRouter()
const goBack = () => {
    // window.history.back()
    if(props.back){
        goto(props.back)
    }else{
        goback()
    }
}

</script>
<template>
    <div class="flex h-14 border-b items-center p-4 gap-4">
        <!-- <router-link v-if="back" :to="back">
            <div class="reset w-6 h-6 flex items-center justify-center">
                <img
                    src="../assets/icon-back.png"
                    alt=""
                    class="filter brightness-10 h-4"
                />
            </div>
        </router-link> -->
        <div v-if="home == false" @click="goBack">
            <div class="reset w-6 h-6 flex items-center justify-center">
                <img
                    src="../assets/icon-back.png"
                    alt=""
                    class="filter brightness-10 h-4"
                />
            </div>
        </div>
        <div v-else>
            <img src="../assets/logo_pelangi.png" alt="" class="h-8" />
        </div>
        <strong class="text-black">{{ title || "title" }}</strong>
        <!-- <div class="ml-auto" v-if="!back">
            <router-link   :to="'/profile'"
                class="h-10 w-10 flex items-center justify-center text-black rounded-full bg-[#00000011]"
            >
                <i class="ri-user-line"></i>
            </router-link>
        </div> -->
        <div class="ml-auto" v-if="home == true">
            <router-link  :to="'/login'">
                <button class="h-10 w-10 flex items-center justify-center text-app-500 rounded-full">
                    <i class="ri-logout-circle-r-line text-xl"></i>
                </button>
            </router-link>
        </div>
    </div>
</template>
