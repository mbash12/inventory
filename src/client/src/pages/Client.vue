<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import { loading } from "../services/router";
import { useRouter, useRoute } from "vue-router";
import Alert from "../components/Alert.vue";
import {
    getDelivery,
    getMisc,
    createDelivery,
    updateDelivery,
    getClientList,
    getSnippet,
    nom,
getClient,
updateClient,
createClient,
} from "../services/service";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import ItemModal from "../components/ItemModal.vue";

const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const route = useRoute();
const router = useRouter();
const state = reactive({
    id: null,
    mode: "add",
    name: null
});
onMounted(() => {
    if (route.path.includes("edit")) {
        loading();
        state.id = route.params.id;
        state.mode = "edit";
        getClient(state.id).then(async (r) => {
            loading(false)
            if (r.code === 200) {
                state.name = r.data.name
            }
        });
    }
});
const submit = () => {
    
    let data = {
        name: state.name
    };
    loading();
    if (state.id) {
        updateClient(state.id, data).then((r) => {
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowError.value = r.message;
            }
            loading(false);
        });
    } else {
        createClient(data).then((r) => {
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowError.value = r.message;
            }
            loading(false);
        });
    }
};
</script>
<template>
    <form @submit.prevent="submit" class="flex flex-col h-full text-black">
        <Navbar :title="`${state.mode=='add' ? 'Add New' : 'Edit'} Client`" :back="'/delivery/'"></Navbar>
        <div class="flex-1 flex flex-col overflow-auto">
            <div class="flex-1 flex flex-col">
                <div
                    class="min-h-full min-w-full flex flex-col bg-gray-50 flex-1"
                >
                    <div
                        class="rounded-none bg-white border-b flex flex-col py-2"
                    >
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Name</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.name"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="px-4 py-2 flex items-center justify-center w-full bg-white border-t"
        >
            <button
                class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
            >
                <i class="ri-save-line text-xl"></i>
                <span class="text-sm mt-1"> Save </span>
            </button>
        </div>
    </form>
    <Alert
        type="fail"
        title="Failed to save"
        :content="alertShowError ?? ''"
        :show="alertShowError != false"
        @hide="alertShowError = false"
    ></Alert>
    <Alert
        type="success"
        title="Success to save"
        :content="'Client saved successfully'"
        :show="alertShowSuccess != false"
        @hide="
            () => {
                alertShowSuccess = false;
                router.back();
            }
        "
    ></Alert>
</template>
