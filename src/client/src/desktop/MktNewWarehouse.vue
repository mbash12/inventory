<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    getWarehouse,
    createWarehouse,
    updateWarehouse,
} from "../services/service";
import { loading } from "../services/router";
import Alert from "../components/Alert.vue";
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);

const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    errors: [],
    id: null,
    name: null,
});

const submit = () => {
    loading();
    let data = {
        name: state.name,
    };
    if (state.id === null) {
        createWarehouse(data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowFailed.value = true;
                state.errors = r.errors;
            }
        });
    } else {
        updateWarehouse(state.id, data).then((r) => {
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
        getWarehouse(id).then((r) => {
            loading(false);
            if (r.code === 200) {
                let data = r.data;
                state.id = data.id;
                state.name = data.name;
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
            {{
                currentUrl.includes("add")
                    ? "Add New Warehouse"
                    : "Edit Warehouse"
            }}
        </div>
        <form
            @submit.prevent="submit"
            class="w-full bg-white rounded-lg shadow p-8"
        >
            <div class="flex gap-8">
                <div class="w-full">
                    <strong class="block mb-4 text-lg text-left"
                        >Warehouse Details</strong
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
                                <i class="ri-store-3-line"></i>
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
            </div>
            <div>
                <div class="flex justify-end gap-4 mt-8">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        type="button"
                        @click="router.push('/desktop/warehouses')"
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
        title="Warehouse Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                router.push('/desktop/warehouses');
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
