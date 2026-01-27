<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getUser, createUser, updateUser } from "../services/service";
import { loading } from "../services/router";
import Alert from "../components/Alert.vue";
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);

const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    password_visible: false,
    errors: [],
    id: null,
    name: null,
    email: null,
    position: null,
    password: null,
});

const submit = () => {
    loading();
    let data = {
        name: state.name,
        email: state.email,
        position: state.position,
    };
    if (state.password) {
        data.password = state.password;
    }
    if (state.id === null) {
        createUser(data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowFailed.value = true;
                state.errors = r.errors;
            }
        });
    } else {
        updateUser(state.id, data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowFailed.value = true;
                state.errors = r.errors;
            }
        });
    }
};
onMounted(() => {
    if (currentUrl.includes("edit")) {
        loading();
        let id = currentUrl.split("/").slice(-1);
        getUser(id).then((r) => {
            loading(false);
            if (r.code === 200) {
                let data = r.data;
                if (data.id === 1) {
                    router.push("/desktop/users");
                }
                state.id = data.id;
                state.name = data.name;
                state.email = data.email;
                state.position = data.position;
            } else {
                state.errors = r.errors;
            }
        });
    }
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            {{ currentUrl.includes("add") ? "Add New User" : "Edit User" }}
        </div>
        <form
            autocomplete="off"
            @submit.prevent="submit"
            class="w-full bg-white rounded-lg shadow p-8"
        >
            <div class="flex gap-8">
                <div class="w-1/2">
                    <strong class="block mb-4 text-lg text-left"
                        >User Details</strong
                    >

                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Name</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                required
                                v-model="state.name"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <!-- <span class="text-xs text-red-500"
                                >Lorem ipsum dolor, sit amet consectetur
                                adipisicing elit. Dolores, cumque.</span
                            > -->
                        </div>
                    </label>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Position</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <select
                                class="bg-transparent w-full h-full rounded-l-lg pl-45px mr-2 text-14px"
                                required
                                v-model="state.position"
                            >
                                <option value=""></option>
                                <option value="admin">Admin</option>
                                <option value="marketing">Marketing</option>
                                <option value="delivery">Delivery</option>
                                <option value="finance">Finance</option>
                                <option value="design">Design</option>
                            </select>
                            <!-- <button
                                class="h-45px w-55px flex items-center justify-center bg-red-500 text-white rounded-r-lg"
                            >
                                <i class="ri-pencil-line"></i>
                            </button> -->
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-pass-valid-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <!-- <span class="text-xs text-red-500"
                                >Lorem ipsum dolor, sit amet consectetur
                                adipisicing elit. Dolores, cumque.</span
                            > -->
                        </div>
                    </label>
                </div>
                <div class="w-1/2">
                    <strong class="block mb-4 text-lg text-left"
                        >Login Details</strong
                    >

                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Account</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                required
                                v-model="state.email"
                                autocomplete="off"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-mail-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('email')"
                                >{{ state.errors.email[0] }}</span
                            >
                        </div>
                    </label>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Password</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                :type="
                                    state.password_visible ? 'text' : 'password'
                                "
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                autocomplete="new-password"
                                :required="
                                    currentUrl.includes('add') ? 'true' : false
                                "
                                @change="
                                    (e) => (state.password = e.target.value)
                                "
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-lock-line"></i>
                            </div>
                            <div
                                class="absolute right-2 text-gray-500 text-xl cursor-pointer p-2"
                                @click="
                                    state.password_visible =
                                        !state.password_visible
                                "
                            >
                                <i class="ri-eye-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('password')"
                                >{{ state.errors.password[0] }}</span
                            >
                        </div>
                    </label>
                </div>
            </div>
            <div>
                <div class="flex justify-end gap-4 mt-8">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        type="button"
                        @click="router.push('/desktop/users')"
                    >
                        <i class="ri-close-circle-line text-xl"></i>
                        <strong>Cancel</strong>
                    </button>
                    <button
                        class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    >
                        <i class="ri-save-line text-xl"></i>
                        <strong>{{
                            currentUrl.includes("add") ? "Save" : "Update"
                        }}</strong>
                    </button>
                </div>
            </div>
        </form>
    </div>
    <Alert
        type="success"
        title="User Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                router.push('/desktop/users');
            }
        "
    ></Alert>
    <Alert
        type="failed"
        title="Unable to save data"
        content="Please check submitted data"
        :show="alertShowFailed"
        @hide="alertShowFailed = false"
    ></Alert>
</template>
