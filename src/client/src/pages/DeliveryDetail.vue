<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import Alert from "../components/Alert.vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import BottomsheetDate from "../components/BottomsheetDate.vue";
import BottomsheetItem from "../components/BottomsheetItem.vue";
import ItemModal from "../components/ItemModal.vue";
import { loading } from "../services/router";
import {
    getDelivery,
    updateDelivery,
    nom,
    ASSETSURL,
    upload
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import BulkDelivery from "../components/BulkDelivery.vue";
const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const route = useRoute();
const router = useRouter();
const state = reactive({
    project_id: null,
    id: null,
    data: null,
    selected: null,
    bulkadd: false,
    bulkdata: null,
    viewFile: null,
    receipt_files: [],
});
const editMode = ref(false);
const handleEditMode = () => {
    editMode.value = true;
};
const handleAdd = (data) => {
    state.bulkadd = false;
    data.forEach((el) => {
        let index = state.data.delivery_items_data.findIndex(
            (e) => e.id == el.id
        );
        if (el.qty && el.qty > 0) {
            state.data.delivery_items_data[index].actual_quantity = el.qty;
            state.data.delivery_items_data[index].delivered_at = new Date();
        } else {
            state.data.delivery_items_data[index].actual_quantity =
                state.data.delivery_items_data[index].quantity;
            state.data.delivery_items_data[index].delivered_at = null;
        }
    });
};
const selectedEdit = ref(null);
const handleDateChange = (i) => {
    state.data.delivery_date = i;
    editMode.value = false;
};
const handleSelected = (i) => {
    state.selected = i;
};
const handleUpdateItem = (e) => {
    state.data.delivery_items_data[state.selected].actual_quantity = e;
    state.selected = null;
};
const handleDeliverItem = () => {
    state.data.delivery_items_data[state.selected].delivered_at = new Date();
    state.selected = null;
};

const removeFile = (i) => {
    state.receipt_files.splice(i, 1)
}

const uploadFile = (e) => {
    
    const file = e.target.files[0];
    if (file.size > 4 * 1024 * 1024) {
        alertShowError.value = "File size must be less than 4MB";
        return;
    }

    const types = ["image/jpeg", "image/jpg", "image/png", "application/pdf"];
    if (!types.includes(file.type)) {
        alertShowError.value = "Only image and document files are allowed";
        return;
    }
    loading();
    upload(file).then((r) => {
        state.receipt_files = [...state.receipt_files, r.path]
        loading(false);
    });
}
onMounted(() => {
    loading();
    state.id = route.params.id;
    getDelivery(state.id).then((r) => {
        if (r.code === 200) {
            state.data = r.data;
            state.data.do_files = JSON.parse(r.data.do_files ?? "[]");
            state.receipt_files = JSON.parse(r.data.receipt_files ?? "[]");
            if (state.data.do_file) {
                state.data.do_files.push(state.data.do_file);
            }
            state.bulkdata = JSON.parse(
                JSON.stringify(r.data.delivery_items_data)
            );
        }
        loading(false);
    });
});
const submit = () => {
    let data = {
        ...state.data,
        receipt_files: JSON.stringify(state.receipt_files),
        delivery_items: state.data.delivery_items_data,
    };
    loading();
    updateDelivery(state.id, data).then((r) => {
        if (r.code === 200) {
            alertShowSuccess.value = true;
        } else {
            alertShowError.value = true;
        }
        loading(false);
    });
};
</script>
<template>
    <form
        @submit.prevent="submit"
        class="flex flex-col h-full text-black min-h-0"
    >
        <Navbar title="Delivery Detail"></Navbar>
        <a
            :href="`/#/details/${state.data?.project}`"
            target="_blank"
            class="flex justify-between px-4 py-2 items-center border-b"
        >
            <div class="flex gap-1 items-center h-6">
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.data?.project_data?.client_po_number !== null"
                    >#PO {{ state.data?.project_data?.client_po_number }}</span
                >
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.data?.project_data?.client_po_number === null"
                    >#PO Dalam Proses</span
                >
                <i
                    v-show="state.data?.project_data?.client_po_number === null"
                    class="ri-error-warning-fill text-lg text-app-500"
                ></i>
            </div>
            <div class="flex gap-2 items-center h-7">
                <span class="text-xs text-black"
                    >#JOB {{ state.data?.project_data?.job_number }}</span
                >
            </div>
        </a>
        <div class="flex-1 flex flex-col min-h-0">
            <div class="flex-1 flex flex-col min-h-0">
                <div
                    class="min-h-full min-w-full flex flex-col bg-gray-50 flex-1"
                >
                    <div class="rounded-none bg-white border-b">
                        <div class="flex gap-1 justify-start items-center">
                            <div
                                :class="`h-20 w-20 flex-shrink-0 flex items-center justify-center flex-col  ${
                                    state.data?.status == 'partial'
                                        ? 'bg-blue-100 text-blue-600 border-blue-600'
                                        : state.data?.status == 'ready'
                                        ? 'bg-yellow-100 text-yellow-600 border-yellow-600'
                                        : state.data?.status == 'delivered'
                                        ? 'bg-green-100 text-green-600 border-green-600'
                                        : ''
                                }`"
                            >
                                <span class="text-3xl font-bold">{{
                                    dayjs(state.data?.delivery_date).format(
                                        "DD"
                                    )
                                }}</span>
                                <span class="text-sm">{{
                                    dayjs(state.data?.delivery_date).format(
                                        "MMM YYYY"
                                    )
                                }}</span>
                            </div>
                            <div class="flex-1 flex flex-col items-start p-2">
                                <div
                                    :class="` rounded-full text-xs   items-center justify-center flex capitalize ${
                                        state.data?.status == 'partial'
                                            ? 'text-blue-600 '
                                            : state.data?.status == 'ready'
                                            ? '  text-yellow-600 '
                                            : state.data?.status == 'delivered'
                                            ? '  text-green-600 '
                                            : ''
                                    }`"
                                >
                                    {{ state.data?.status }}
                                </div>
                                <span class="font-semibold text-sm"
                                    ># {{ state.data?.do_number }}</span
                                >
                                <span class="text-xs text-blue-gray-500"
                                    >{{ nom(state.data?.delivered ?? 0) }}/{{
                                        nom(state.data?.total)
                                    }}
                                    items delivered</span
                                >
                            </div>
                            <div v-if="state.data?.status !== 'delivered'">
                                <button
                                    type="button"
                                    class="p-4 flex items-center justify-center"
                                    @click="handleEditMode"
                                >
                                    <i class="ri-edit-line text-xl"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex justify-between px-4 py-3 items-center border-b"
                        v-if="state.data?.do_files?.length"
                    >
                        <span class="text-xs text-blue-gray-600 font-semibold"
                            >Surat Jalan</span
                        >
                    </div>
                    <div
                        class="flex gap-2 overflow-x-auto px-4 py-2 border-b"
                        v-if="state.data?.do_files?.length"
                    >
                        <div
                            class="flex items-center w-80px h-80px flex-shrink-0 rounded border relative"
                            v-for="(file, i) in state?.data?.do_files"
                            @click="state.viewFile = file"
                        >
                            <img
                                :src="ASSETSURL + file"
                                alt=""
                                class="w-full h-full object-cover"
                            />
                        </div>
                    </div>

                    <div
                        class="flex justify-between px-4 py-3 items-center border-b"
                    >
                        <span class="text-xs text-blue-gray-600 font-semibold"
                            >Foto Barang Diterima</span
                        >
                    </div>
                    <div class="flex gap-2 overflow-auto w-full p-2 border-b">
                        <div
                            class="w-80px h-80px flex-shrink-0 relative border rounded flex flex-col items-center justify-center"
                            v-if="state.data?.status !== 'delivered'"
                        >
                            <i class="ri-upload-line text-2xl"></i>
                            <span class="text-xs text-blue-gray-400"
                                >Upload</span
                            >
                            <input
                                type="file"
                                class="w-full h-full opacity-0 absolute inset-0"
                                @input="uploadFile"
                                accept="image/*"
                            />
                        </div>
                        <div
                            class="flex items-center w-80px h-80px flex-shrink-0 rounded border relative"
                            v-for="(file, i) in state?.receipt_files"
                            @click="state.viewFile = file"
                        >
                            <img
                                :src="ASSETSURL + file"
                                alt=""
                                class="w-full h-full"
                            />
                            <i
                                class="ri-close-circle-fill text-red-500 text-3xl absolute -top-2 w-28px h-28px block flex items-center justify-center -right-2"
                                v-if="state.data?.status !== 'delivered'"
                                @click.stop="removeFile(i)"
                            ></i>
                        </div>
                    </div>
                    <div
                        class="flex justify-between px-4 py-3 items-center border-b"
                    >
                        <span class="text-xs text-blue-gray-600 font-semibold"
                            >Logistik</span
                        >
                        <span
                            class="text-xs text-blue-gray-600 font-semibold"
                            >{{ state.data?.shipping_vendor_data?.name }}</span
                        >
                    </div>
                    <div class="flex-1 overflow-auto">
                        <button
                            type="button"
                            v-for="(item, i) in state.data?.delivery_items_data"
                            :key="i"
                            class="flex flex-col items-center w-full px-4 bg-white py-3 gap-1 border-b"
                            @click="
                                () =>
                                    item.delivered_at === null
                                        ? handleSelected(i)
                                        : null
                            "
                        >
                            <div class="flex justify-between w-full text-sm">
                                <span class="text-left leading-4">
                                    {{ item.product_data?.name }}
                                </span>
                                <strong class="leading-4">{{
                                    nom(item.actual_quantity)
                                }}</strong>
                            </div>

                            <div class="flex justify-between w-full text-sm">
                                <span
                                    class="flex justify-start w-full text-sm text-app-900 items-center"
                                >
                                    By {{ item.origin_data?.name }}
                                </span>
                                <span
                                    class="flex justify-end w-full text-xs text-app-100 items-center gap-1"
                                    v-if="item.delivered_at !== null"
                                >
                                    <i
                                        class="ri-checkbox-circle-line text-lg"
                                    ></i>
                                    Delivered
                                </span>
                            </div>
                        </button>
                        <div class="p-4 w-full mb-4 flex gap-4">
                            <button
                                type="button"
                                class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                                @click="state.bulkadd = true"
                            >
                                <span class="text-sm"> Bulk Delivery </span>
                            </button>
                        </div>
                    </div>
                    <div
                        class="px-4 py-3 text-left flex items-center justify-between gap-1 border-t bg-white shadow-md shadow-gray-100"
                    >
                        <span class="text-sm flex-1 text-left">
                            {{ state.data?.default_origin_data?.name }}
                        </span>
                        <span class="text-xl flex-1 text-center text-gray-400">
                            <i class="ri-arrow-right-circle-line"></i>
                        </span>
                        <span class="text-sm flex-1 text-right">
                            {{ state.data?.destination_data?.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="px-4 py-2 flex items-center justify-center w-full bg-white border-t"
            v-if="state.data?.status !== 'delivered'"
        >
            <button
                class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
            >
                <i class="ri-save-line text-xl"></i>
                <span class="text-sm mt-1"> Save </span>
            </button>
        </div>
    </form>
    <BottomsheetDate
        :show="editMode"
        :value="state.data?.delivery_date"
        @changed="handleDateChange"
        @hide="editMode = false"
    ></BottomsheetDate>
    <BottomsheetItem
        :show="state.selected !== null"
        :actual_quantity="
            state.data?.delivery_items_data[state.selected]?.actual_quantity
        "
        :delivered_at="
            state.data?.delivery_items_data[state.selected]?.delivered_at
        "
        @hide="state.selected = null"
        @update="handleUpdateItem"
        @deliver="handleDeliverItem"
    ></BottomsheetItem>
    <ItemModal
        :show="selectedEdit !== null"
        @hide="selectedEdit = null"
    ></ItemModal>
    <BulkDelivery
        :show="state.bulkadd"
        :items="state.bulkdata"
        @hide="state.bulkadd = false"
        @bulkadd="handleAdd"
    ></BulkDelivery>
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
        :content="'Delivery saved successfully'"
        :show="alertShowSuccess != false"
        @hide="
            () => {
                alertShowSuccess = false;
                router.back();
            }
        "
    ></Alert>

    <div
        class="fixed bottom-0 left-0 right-0 top-0 z-60 bg-black/70 flex items-center justify-center"
        v-if="state.viewFile"
    >
        <img
            :src="ASSETSURL + state.viewFile"
            alt=""
            class="max-w-full max-h-full object-contain"
        />
        <i
            class="ri-close-circle-fill text-red-500 text-4xl absolute top-2 w-32px h-32px block flex items-center justify-center right-2 bg-white rounded-full"
            @click="state.viewFile = null"
        ></i>
    </div>
</template>
