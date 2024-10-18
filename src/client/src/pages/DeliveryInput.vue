<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import { loading } from "../services/router";
import { useRouter, useRoute } from "vue-router";
import Alert from "../components/Alert.vue";
import BulkAdd from "../components/BulkAdd.vue";
import Select3 from "../components/Select3.vue";
import {
    getDelivery,
    getMisc,
    createDelivery,
    updateDelivery,
    getShippingVendorList,
    getSnippet,
    nom,
    upload,
    ASSETSURL,
} from "../services/service";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import ItemModal from "../components/ItemModal.vue";

const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const route = useRoute();
const router = useRouter();
const state = reactive({
    project_id: null,
    id: null,
    mode: "add",
    misc: null,
    shipping_vendors: [],
    delivery_date: null,
    do_number: null,
    default_origin: null,
    destination: null,
    shipping_vendor: null,
    shipping_vendor_name: null,
    items: [],
    selected: null,
    edit_open: false,
    bulkadd:false,
    project: null,
    do_file:null,
    do_files:[],
    viewFile:null
});
const getMiscData = async () => {
    return new Promise((resolve) => {
        getMisc(state.project_id).then((r) => {
            if (r.code === 200) {
                state.misc = r.data;
            }
            loading(false);
            resolve();
        });
    });
};
const closeModal = () => {
    state.edit_open = false;
    state.selected = null;
};
const handleBulkAdd = () => {
    state.bulkadd = !state.bulkadd
}
const handleAdd = (data) => {
    let products = data.products.filter(e=>e.qty > 0).map((e) => {
        return { product: e.id, origin: data.origin, quantity: e.qty, delivered_at: null };
    })
    state.items = [...products];
    state.bulkadd = false
}
const handleSelected = (i = null) => {
    state.edit_open = true;
    if (i !== null) {
        state.selected = i;
    }
};
const handleDelete = () => {
    state.edit_open = false;
    state.items.splice(state.selected, 1);
    state.selected = null;
};
const handleAction = (data) => {
    state.edit_open = false;
    if (data !== null) {
        if (state.selected !== null) {
            state.items[state.selected] = {
                ...state.items[state.selected],
                ...data,
            };
        } else {
            let exist = state.items.findIndex(
                (e) => e.origin === data.origin && e.product === data.product
            );
            if (exist >= 0) {
                state.items[exist] = { ...state.items[exist], ...data };
            } else {
                state.items = [...state.items, { ...data }];
            }
        }
    }
    state.selected = null;
};
const getSnip = async () => {
    return new Promise((resolve) => {
        getSnippet(state.project_id).then((r) => {
            if (r.code === 200) {
                state.project = r.data;
                state.default_origin = r.data.manufacture;
            }
            resolve();
        });
    });
};
onMounted(() => {
    loading();
    getShippingVendorList().then((r) => {
        if (r.code === 200) {
            state.shipping_vendors = r.data;
        }
    });
    if (route.path.includes("edit")) {
        state.id = route.params.id;
        state.mode = "edit";
        getDelivery(state.id).then(async (r) => {
            if (r.code === 200) {
                state.project_id = r.data.project;
                await getSnip();
                getMiscData().then(() => {
                    state.id = r.data.id;
                    state.delivery_date = dayjs(r.data.delivery_date).format(
                        "YYYY-MM-DD"
                    );
                    state.do_number = r.data.do_number;
                    state.do_file = r.data.do_file;
                    state.do_files = r.data.do_files;
                    state.default_origin = r.data.default_origin;
                    state.destination = r.data.destination;
                    state.shipping_vendor = r.data.shipping_vendor;
                    state.items = r.data.delivery_items_data;
                });
            }
        });
    } else {
        state.project_id = route.params.id;
        getSnip();
        getMiscData();
    }
});
const removeFile = (i) => {
    state.do_files.splice(i, 1)
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
        state.do_files = [...state.do_files, r.path]
        loading(false);
    });
}

const submit = () => {
    if (state.items.length === 0) {
        alertShowError.value = "Please required fileds";
        return;
    }
    let data = {
        project: state.project_id,
        default_origin: state.default_origin,
        destination: state.destination,
        shipping_vendor: state.shipping_vendor,
        delivery_date: state.delivery_date,
        do_number: state.do_number,
        // do_file: state.do_file,
        do_files: JSON.stringify(state.do_files),
        delivery_items: state.items.map((e) => ({
            id: e.id,
            origin: e.origin,
            destination: state.destination,
            product: e.product,
            quantity: e.quantity,
            actual_quantity: e.quantity,
            delivery_date: state.delivery_date,
            delivered_at: e.delivered_at ?? null,
        })),
    };
    loading();
    if (state.id) {
        updateDelivery(state.id, data).then((r) => {
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowError.value = r.message;
            }
            loading(false);
        });
    } else {
        createDelivery(data).then((r) => {
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
        <Navbar title="New Delivery" :back="`/details/${state.id}`"></Navbar>
        <a
            :href="`/#/details/${state.project_id}`"
            target="_blank"
            class="flex justify-between px-4 py-2 items-center border-b"
        >
            <div class="flex gap-1 items-center h-6">
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.project?.client_po_number !== null"
                    >#PO {{ state.project?.client_po_number }}</span
                >
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.project?.client_po_number === null"
                    >#PO Dalam Proses</span
                >
                <i
                    v-show="state.project?.client_po_number === null"
                    class="ri-error-warning-fill text-lg text-app-500"
                ></i>
            </div>
            <div class="flex gap-2 items-center h-7">
                <span class="text-xs text-black"
                    >#JOB {{ state.project?.job_number }}</span
                >
            </div>
        </a>
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
                                        >Delivery Date</span
                                    >
                                    <input
                                        type="date"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.delivery_date"
                                    />
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Surat Jalan</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.do_number"
                                    />
                                </label>
                            </div>
                        </div>
                        <div class="flex py-2 justify-between w-full">
                            <div class="flex flex-col items-start flex-1 w-full">
                                <div
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Foto Surat Jalan</span
                                    >
                                    <div class="flex gap-2 overflow-auto w-full py-2">
                                        <div class="w-80px h-80px flex-shrink-0 relative border rounded flex flex-col items-center justify-center">
                                            <i class="ri-upload-line text-2xl"></i>
                                            <span class="text-xs text-blue-gray-400">Upload</span>
                                            <input
                                                type="file"
                                                class="w-full h-full opacity-0 absolute inset-0"
                                                @input="uploadFile"
                                                accept="image/*"
                                            />
                                        </div>
                                        <div class="flex items-center w-80px h-80px  flex-shrink-0 rounded border relative" v-for="(file, i) in state?.do_files" @click="state.viewFile = file">
                                            <img :src="ASSETSURL + file" alt="" class="w-full h-full" >
                                            <i class="ri-close-circle-fill text-red-500 text-3xl absolute -top-2 w-28px h-28px block flex items-center justify-center -right-2" @click.stop="removeFile(i)"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Default Origin</span
                                    >
                                    <select
                                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                                        v-model="state.default_origin"
                                    >
                                        <option value="" disabled selected>
                                            Select Origin
                                        </option>
                                        <option
                                            v-for="(
                                                option, i
                                            ) in state.misc?.warehouses.filter(
                                                (e) => e.id !== 2
                                            )"
                                            :value="option.id"
                                            :key="i"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Destination</span
                                    >
                                    <select
                                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                                        v-model="state.destination"
                                    >
                                        <option value="" disabled selected>
                                            Select Destination
                                        </option>
                                        <option
                                            v-for="(
                                                option, i
                                            ) in state.misc?.warehouses.filter(
                                                (e) =>
                                                    e.id !=
                                                        state.default_origin &&
                                                    e.id !== 1
                                            )"
                                            :value="option.id"
                                            :key="i"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                        </div>
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Shipping Vendor</span
                                    >

                                    <Select3
                                        @select="
                                            (e) => {state.shipping_vendor = e.value; state.shipping_vendor_name = e.label}
                                        "
                                        :value="state.shipping_vendor"
                                        :alias="state.shipping_vendor_name"
                                        required="true"
                                    />
                                    <!-- <select
                                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                                        v-model="state.shipping_vendor"
                                    >
                                        <option value="" disabled selected>
                                            Select Shipping Vendor
                                        </option>
                                        <option
                                            v-for="(
                                                option, i
                                            ) in state.shipping_vendors"
                                            :value="option.id"
                                            :key="i"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select> -->
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-2 text-sm font-bold">Items</div>
                    <div class="h-full">
                        <button
                            type="button"
                            v-for="(item, i) in state.items"
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
                                <span>
                                    {{
                                        state.misc.products.find(
                                            (e) => e.id === item.product
                                        ).name
                                    }}
                                </span>
                                <strong
                                    >{{ nom(item.quantity) }} /
                                    {{
                                        nom(
                                            state.misc.inventories.find(
                                                (e) =>
                                                    e.product ===
                                                        item.product &&
                                                    e.warehouse === item.origin
                                            ).quantity
                                        )
                                    }}</strong
                                >
                            </div>
                            <div
                                class="flex justify-start w-full text-sm text-app-900"
                            >
                                By
                                {{
                                    state.misc.warehouses.find(
                                        (e) => e.id === item.origin
                                    ).name
                                }}
                            </div>
                        </button>

                        <div
                            class="p-4 w-full mb-4 flex gap-4"
                            v-if="
                                state.delivery_date !== null &&
                                state.do_number !== null &&
                                state.default_origin !== null &&
                                state.destination !== null
                            "
                        >
                            <button
                                type="button"
                                class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                                @click="handleSelected()"
                            >
                                <i class="ri-add-line text-xl"></i>
                                <span class="text-sm"> Add more item </span>
                            </button>
                            <button
                                type="button"
                                class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                                @click="handleBulkAdd()"
                            >
                                <span class="text-sm"> Bulk Add </span>
                            </button>
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
    <BulkAdd 
        :show="state.bulkadd"
        :items="state.items"
        :misc="state.misc"
        :default_origin="state.default_origin"
        @hide="state.bulkadd = false"
        @bulkadd="handleAdd"
        >
    </BulkAdd>
    <ItemModal
        :show="state.edit_open"
        :items="state.items"
        :misc="state.misc"
        :selected="state.selected"
        :default_origin="state.default_origin"
        @hide="closeModal"
        @action="handleAction"
        @delete="handleDelete"
    ></ItemModal>
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
    
    <div class="fixed bottom-0 left-0 right-0 top-0 z-60 bg-black/70 flex items-center justify-center" v-if="state.viewFile">
        <img :src="ASSETSURL + state.viewFile" alt="" class="max-w-full max-h-full object-contain">
        <i class="ri-close-circle-fill text-red-500 text-4xl absolute top-2 w-32px h-32px block flex items-center justify-center right-2 bg-white rounded-full"
            @click="state.viewFile = null"></i>

    </div>
</template>
