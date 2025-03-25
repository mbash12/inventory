<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import {
    getProject,
    getShippingVendorList,
    getWarehouseList,
    nom,
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import ShippingVendor from "../components/ShippingVendor.vue";
import UpdateProduction from "../components/UpdateProduction.vue";
const router = useRouter();
const route = useRoute();
const showProduct = ref(false);
const state = reactive({
    id: null,
    data: null,
    shipping_vendors: [],
    warehouses: [],
    shipping_open: false,
    thread_open:false,
    pos: null,
});
const loadData = () => {

    loading();
    state.id = route.params.id;
    getShippingVendorList().then((r) => {
        if (r.code === 200) {
            state.shipping_vendors = r.data;
        }
    });
    getWarehouseList().then((r) => {
        if (r.code === 200) {
            state.warehouses = r.data;
        }
    });
    getProject(state.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.pos = r.data.po_deposit_data?.purchase_ordres
                ? JSON.parse(r.data.po_deposit_data.purchase_ordres)
                : null;
        }
    });
}
onMounted(() => {
    loadData();
});
</script>
<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Details" :back="'/deliveries/?filter=true'"></Navbar>
        <div class="flex-1 flex flex-col min-h-0">
            <div class="flex-1 overflow-auto min-h-0">
                <div class="min-h-full min-w-full flex flex-col bg-gray-50">
                    <div
                        class="rounded-none bg-white shadow-md shadow-gray-100"
                    >
                        <div
                            class="flex justify-between px-4 pt-2 items-center"
                        >
                            <div class="flex gap-1 items-center h-6">
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="
                                        state.data?.client_po_number !== null
                                    "
                                    >#PO
                                    {{ state.data?.client_po_number }}</span
                                >
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="
                                        state.data?.client_po_number === null
                                    "
                                    >#PO Dalam Proses</span
                                >
                                <i
                                    v-show="
                                        state.data?.client_po_number === null
                                    "
                                    class="ri-error-warning-fill text-lg text-app-500"
                                ></i>
                            </div>
                            <div class="flex gap-2 items-center h-7">
                                <span class="text-xs text-black"
                                    >#JOB {{ state.data?.job_number }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex justify-between gap-1 px-4 items-start"
                        >
                            <div class="flex w-full flex-col">
                                <div
                                    class="flex items-center gap-1 my-2 flex-wrap"
                                >
                                    <span
                                        class="font-medium text-gray-400 text-xs leading-4 pr-2 w-full"
                                        >{{ state.data?.title }}</span
                                    >
                                    <span
                                        class="font-semibold text-black text-sm pr-2 leading-4"
                                        >{{
                                            state.data?.products_data[0]?.name
                                        }}</span
                                    >
                                    <span
                                        class="text-blue-gray-500 text-xs"
                                        v-if="
                                            state.data?.products_data.length > 1
                                        "
                                        >+{{
                                            state.data?.products_data.length - 1
                                        }}
                                        other(s)</span
                                    >
                                </div>
                                <span class="text-app-900 text-xs text-left"
                                    >by {{ state.data?.client_company }}</span
                                >
                            </div>
                            <div
                                class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
                                :class="
                                    state.data?.status === 'delivered'
                                        ? 'bg-[#6FD669]'
                                        : state.data?.status === 'partial'
                                        ? 'bg-[#4DC8E3]'
                                        : state.data?.status === 'ready'
                                        ? 'bg-[#E5C100]'
                                        : state.data?.status === 'cancel'
                                        ? 'bg-[#E44545]'
                                        : state.data?.status === 'production'
                                        ? 'bg-[#3f51b5]'
                                        : state.data?.status === 'new'
                                        ? 'bg-[#AD58D4]'
                                        : ''
                                "
                            >
                                {{ state.data?.status }}
                            </div>
                        </div>
                        <div class="border-b w-full mt-2"></div>
                        <div class="px-4 py-2 flex">
                            <div class="flex w-full">
                                <!-- <div class="flex-1 flex flex-col items-start">
                                    <span class="text-xs text-blue-gray-500"
                                        >Produced</span
                                    >
                                    <div class="text-black">
                                        <strong>{{ state.data?.total }}</strong
                                        >/<span>{{ state.data?.total }}</span>
                                    </div>
                                </div> -->
                                <div class="flex-1 flex flex-col items-start">
                                    <span class="text-xs text-blue-gray-500"
                                        >Stored</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(state.data?.stored ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.data?.total)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-center">
                                    <span
                                        class="text-xs text-blue-gray-500 flex-1"
                                        >Delivered</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(state.data?.delivered ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.data?.total)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-end">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoice</span
                                    >
                                    <div
                                        class="text-xs font-medium capitalize"
                                        :class="
                                            state.data
                                                ?.invoice_status == 'sent'
                                                ? 'text-green-500'
                                                : 'text-orange-500'
                                        "
                                    >
                                        <span>{{
                                            state.data
                                                ?.invoice_status ?? 'Not Yet Processed'
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-b w-full mb-2"></div>
                        <div class="px-4 pb-2">
                            <div class="flex py-2">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Pelangi's PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.pic_name
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-end flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Client's PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.client_pic_name
                                    }}</strong>
                                </div>
                            </div>
                            <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >PO Date</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.client_po_date
                                            ? dayjs(
                                                  state.data?.client_po_date
                                              ).format("D MMMM YYYY")
                                            : "Dalam Proses"
                                    }}</strong>
                                </div>
                                <!-- <div class="flex flex-col items-end flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Shipping Vendor</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.shipping_vendors_data?.name
                                    }}</strong>
                                </div> -->
                                <!-- <div class="flex flex-col items-end flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Tanggal Masuk Gudang</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        dayjs(
                                            state.data?.production_start_date
                                        ).format("D MMMM YYYY")
                                    }}</strong>
                                </div> -->
                            </div>
                            <!-- <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >PO</span
                                    >
                                    <div
                                        class="mt-1 flex justify-between w-full items-center"
                                        v-for="invoice in state.pos"
                                    >
                                        <span class="text-sm">
                                            {{ invoice.client_po_number }}
                                        </span>
                                        <span class="text-xs">
                                            {{ invoice.client_po_date }}
                                        </span>
                                    </div>
                                </div>
                            </div> -->
                        </div>
                    </div>
                    <button
                        class="px-4 py-3 flex justify-between items-center"
                        @click="showProduct = !showProduct"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Products
                            <span
                                >({{ state.data?.products_data.length }})</span
                            ></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showProduct">
                        <div
                            class="flex flex-col py-3 border-b"
                            v-for="(item, i) in state.data?.products_data"
                            :key="i"
                        >
                            <div class="flex justify-between text-sm gap-2">
                                <strong class="leading-4">{{
                                    item.name
                                }}</strong>
                                <span class="flex-shrink-0 whitespace-nowrap"
                                    >{{ nom(item.delivered) }} /
                                    {{ nom(item.quantity) }}</span
                                >
                            </div>
                            <div class="text-xs mt-1 text-gray-500">
                                {{ item.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="px-4 py-2 flex items-center justify-center w-full border-t"
            >
                <button
                    v-if="
                        !state.data?.manufacture && state.data?.status == 'new'
                    "
                    class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                    @click="state.thread_open = true"
                >
                    <i class="ri-truck-line text-xl"></i>
                    <span class="text-sm mt-1"> Set To Production </span>
                </button>

                <button
                    v-else-if="
                        !state.data?.manufacture &&
                        state.data?.status == 'production'
                    "
                    class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                    @click="state.shipping_open = true"
                >
                    <i class="ri-truck-line text-xl"></i>
                    <span class="text-sm mt-1"> Ready To Deliver </span>
                </button>
                <button
                    v-else
                    class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                    @click="router.push('/delivery/' + state.id)"
                >
                    <i class="ri-truck-line text-xl"></i>
                    <span class="text-sm mt-1"> Update Delivery Status </span>
                </button>
            </div>
        </div>
    </main>

    <ShippingVendor
        :show="state.shipping_open"
        :project="state.data"
        :shipping_vendors="state.shipping_vendors"
        :warehouses="state.warehouses.filter((e) => ![1, 2].includes(e.id))"
        @hide="state.shipping_open = false"
        @submit="router.push('/delivery/' + state.id)"
    ></ShippingVendor>
    <UpdateProduction
        :show="state.thread_open"
        :project="state.data"
        @hide="state.thread_open = false"
        @submit="loadData(); state.thread_open = false"
    ></UpdateProduction>
</template>
