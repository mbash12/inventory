<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import { loading } from "../services/router";
import {
    currentUser,
    data,
    logout,
    isLoggedin,
    checkLoggedin,
    upload,
    updateProfile,
    getNotificationCount,
} from "../services/service";
import Alert from "../components/Alert.vue";
import { compressImage } from "../utils/imageCompression";
const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const editMode = ref(false);
const error = reactive({
    fullname: false,
});
const editProfile = async () => {
    if (currentUser.user.user.fullname == "") {
        error.fullname = true;
    }
    if (error.fullname) {
        alertShowError.value = "Please input required fields";
        return;
    }
    if (editMode.value === true) {
        loading();
        await updateProfile({
            fullname: currentUser.user.user.fullname,
            photos: currentUser.user.user.photos,
            position: currentUser.user.user.position,
            phone: currentUser.user.user.phone || "",
        });
        loading(false);
        alertShowSuccess.value = true;
    }
    editMode.value = !editMode.value;
};
const uploadPicture = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    loading();
    
    try {
        let processedFile = file;
        
        // Compress image if it's an image file
        if (file.type.startsWith('image/')) {
            processedFile = await compressImage(file, 2000, 0.8);
        }
        
        const result = await upload(processedFile);
        currentUser.user.user.photos = result.media_url;
        loading(false);
    } catch (error) {
        alertShowError.value = error;
        loading(false);
    }
};

const router = useRouter();
const doLogout = async () => {
    loading();
    let result = await logout();
    let result1 = await checkLoggedin();
    loading(false);
    router.push("/login");
};
// getNotificationCount();
</script>

<template>
    <div class="w-full h-full flex flex-col">
        <div class="w-full h-[93px] relative flex-shrink-0">
            <div class="w-full h-full absolute top-0 left-0">
                <img src="../assets/bg-3.png" alt="" class="w-full h-[93px]" />
            </div>
            <div class="w-full h-full absolute top-0 left-0">
                <div
                    class="w-full flex justify-between p-5 h-[93px] items-center"
                >
                    <router-link to="/">
                        <div
                            class="flex-shrink-0 w-10 h-10 flex items-center justify-center"
                        >
                            <img src="../assets/icon-back.png" alt="" />
                        </div>
                    </router-link>
                    <div class="flex flex-col justify-center items-center">
                        <span
                            class="text-white font-inter font-bold text-[20px] mt-1"
                            >Profile</span
                        >
                    </div>
                    <div
                        class="flex-shrink-0 w-10 h-10 flex items-center justify-center"
                    ></div>
                </div>
            </div>
        </div>
        <div class="w-full flex-1 p-6 py-10 flex flex-col overflow-auto">
            <div class="w-full flex flex-col flex-1 gap-6 items-center">
                <div class="w-30 h-30 relative">
                    <div
                        class="w-30 h-30 rounded-full bg-white shadow-2xl p-[6px] overflow-hidden"
                    >
                        <img
                            :src="currentUser.user.user.photos"
                            alt=""
                            class="rounded-full w-full h-full object-cover"
                        />
                    </div>
                    <div
                        class="w-9 h-9 bg-white absolute top-0 right-0 rounded-full overflow-hidden shadow p-[3px]"
                        v-if="editMode === true"
                    >
                        <div
                            class="w-full h-full bg-app-200 rounded-full flex items-center justify-center cursor-pointer"
                        >
                            <img
                                src="../assets/icon-camera.png"
                                alt=""
                                class="cursor-pointer"
                            />
                            <input
                                type="file"
                                class="absolute w-full h-full top-0 left-0 opacity-0 cursor-pointer"
                                accept="image/*"
                                @change="uploadPicture"
                            />
                        </div>
                    </div>
                </div>
                <div class="w-full flex flex-col justify-start">
                    <div
                        class="text-left font-bold text-[15px] text-app-113 py-3"
                    >
                        Biodata Diri
                    </div>
                    <div class="flex py-0">
                        <div
                            class="w-34 text-left text-[14px] font-light text-app-113 flex items-center"
                        >
                            PIC Name
                        </div>
                        <div
                            class="flex-1 flex text-[14px] font-normal items-center text-app-115 h-10 px-2"
                            v-if="!editMode"
                        >
                            {{ currentUser.user.user.fullname }}
                        </div>
                        <input
                            type="text"
                            :class="`border-b bg-gray-100  px-2 flex-1 h-10 text-[14px]  text-black ${
                                error.fullname
                                    ? 'border-red-500'
                                    : 'border-app-600 '
                            }`"
                            v-else
                            v-model="currentUser.user.user.fullname"
                            @input="error.fullname = false"
                        />
                    </div>
                    <div class="flex pt-3">
                        <div
                            class="w-34 text-left text-[14px] font-light text-app-113 flex items-center"
                        >
                            Position
                        </div>
                        <div
                            class="flex-1 flex text-[14px] font-normal items-center text-app-115 h-10 px-2"
                            v-if="!editMode"
                        >
                            {{ currentUser.user.user.position }}
                        </div>
                        <input
                            type="text"
                            class="border-b border-app-600 bg-gray-100 px-2 flex-1 h-10 text-[14px] text-black"
                            v-else
                            v-model="currentUser.user.user.position"
                        />
                    </div>
                    <div class="flex py-3">
                        <div
                            class="w-34 text-left text-[14px] font-light text-app-113 flex items-center"
                        >
                            Company name
                        </div>
                        <div
                            class="flex-1 flex text-[14px] font-normal items-center text-app-115 h-10 px-2 text-black"
                        >
                            {{ currentUser.user.store.name }}
                        </div>
                        <!-- <input type="text" class="border-b border-app-600 bg-gray-100  px-2 flex-1 h-10 text-[14px]" v-else  v-model="currentUser.user.store.name" /> -->
                    </div>
                    <div class="my-4 h-[1px] bg-gray-100"></div>
                    <div
                        class="text-left font-bold text-[15px] text-app-113 py-3"
                    >
                        Contact
                    </div>
                    <div class="flex py-0">
                        <div
                            class="w-34 text-left text-[14px] font-light text-app-113 flex items-center"
                        >
                            Email
                        </div>
                        <div
                            class="flex-1 flex text-[14px] font-normal items-center text-app-115 h-10 px-2 text-black"
                        >
                            {{ currentUser.user.user.email }}
                        </div>
                        <!-- <input type="text" class="border-b border-app-600 bg-gray-100  px-2 flex-1 h-10 text-[14px]" v-else v-model="currentUser.user.user.email" /> -->
                    </div>
                    <div class="flex py-3">
                        <div
                            class="w-34 text-left text-[14px] font-light text-app-113 flex items-center"
                        >
                            Phone number
                        </div>
                        <div
                            class="flex-1 flex text-[14px] font-normal items-center text-app-115 h-10 px-2 text-black"
                            v-if="!editMode"
                        >
                            {{ currentUser.user.user.phone }}
                        </div>
                        <input
                            type="text"
                            class="border-b border-app-600 bg-gray-100 px-2 flex-1 h-10 text-[14px] text-black"
                            v-else
                            v-model="currentUser.user.user.phone"
                        />
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-6 mt-8">
                <div
                    class="w-full h-12 border-[2px] border-app-600 flex items-center justify-center rounded-full cursor-pointer"
                    @click="editProfile"
                    v-if="!editMode"
                >
                    <img src="../assets/icon-edit.png" alt="" />
                    <div class="pl-4 text-app-600 font-semibold text-[16px]">
                        Edit Profile
                    </div>
                </div>
                <div
                    class="w-full h-12 border-[2px] border-app-200 bg-app-600 flex items-center justify-center rounded-full cursor-pointer"
                    @click="editProfile"
                    v-else
                >
                    <div class="pl-4 text-white font-semibold text-[16px]">
                        Save
                    </div>
                </div>
                <div
                    class="w-full h-12 flex items-center justify-center rounded-full cursor-pointer"
                    @click="doLogout"
                >
                    <img src="../assets/icon-logout.png" alt="" />
                    <div class="pl-4 text-app-800 font-semibold text-[16px]">
                        Logout
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Alert
        type="fail"
        title="Failed"
        :content="alertShowError"
        :show="alertShowError"
        @hide="alertShowError = false"
    ></Alert>
    <Alert
        type="success"
        title="Profile updated"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
            }
        "
    ></Alert>
</template>
