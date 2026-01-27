<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import Bottomsheet from "../components/Bottomsheet.vue";
import { loading } from "../services/router";
import { getProjectList, nom } from "../services/service";
import BottomsheetDeliveryFilter from "../components/BottomsheetDeliveryFilter.vue";
import Table from "../components/Table.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const state = reactive({
    data: [],
    meta: {},
    // selected: null,
    filter_open: false,
    search: "",
    order_by: null,
    sort: null,
    status: [],
    po: [],
    start_date: null,
    end_date: null,
    page: 1,
    limit: 10,
    export: null,
    export_mode: "print",
    client: null,
});
const handleFilter = (e) => {
    state.page = 1;
    state.status = e.status;
    state.po = e.po;
    state.start_date = e.start_date;
    state.end_date = e.end_date;
    state.limit = e.limit;
    state.filter_open = false;
    state.client = e.client;
    search();
};
const handleClearFilter = () => {
    state.status = [];
    state.po = [];
    state.start_date = null;
    state.end_date = null;
    state.limit = 10;
    state.filter_open = false;
    state.client = null;
    search();
};

const prevPage = () => {
    if (state.meta) {
        if (state.page - 1 >= 1) {
            state.page = state.page - 1;
            search();
        }
    }
};
const nextPage = () => {
    if (state.meta) {
        if (state.page + 1 <= state.meta?.total_pages) {
            state.page = state.page + 1;
            search();
        }
    }
};
const doSearch = () => {
    state.page = 1;
    search();
};
const search = () => {
    // state.page = 1;
    // state.selected = null;
    loading();
    let filter = {
        search: state.search,
        status: state.status.join(","),
        po: state.po.join(","),
        start_date: state.start_date,
        end_date: state.end_date,
        limit: state.limit,
        page: state.page,
        delivery: true,
        client: state.client
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem("dlv", JSON.stringify(filter));
    getProjectList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }else{
            state.data = [];
            state.meta = {};
        }
    });
};

const prepareExport = (mode) => {
    state.export_mode = mode;
    let status = {
        cancel: "Cancel",
        ready: "Ready To Deliver",
        partial: "Partial Delivery",
        delivered: "Delivered",
    };
    let data = state.data.map((e) => ({
        "Job Number": e.job_number,
        "PO Number": e.client_po_number ?? "Dalam Proses",
        "PO Date": e.client_po_date
            ? dayjs(e.client_po_date).format("DD MMM YYYY")
            : "Dalam Proses",
        "Pelangi's PIC": e.pic_name,
        Client: e.client_company,
        "Client's PIC": e.client_pic_name,
        // "Shipping Vendor": e.shipping_vendors_data.name,
        Products: e.products_data
            .map((ee) => `${ee.name} (${nom(ee.quantity ?? 0)})`)
            .join("<br>"),
        Stored: nom(e.stored ?? 0),
        Delivered: nom(e.delivered ?? 0),
        Status: status[e.status],
    }));

    state.export = data;
};
onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("dlv");
        if (flt) {
            flt = JSON.parse(flt);
            state.status = flt?.status?.split(",") ?? [];
            state.po = flt?.po?.split(",") ?? [];
            state.start_date = flt?.start_date ?? null;
            state.end_date = flt?.end_date ?? null;
            state.limit = flt?.limit ?? null;
            state.page = flt?.page ?? 1;
        }
    } else {
        localStorage.removeItem("dlv");
    }
    search();
});
const submit = (e) => {
    e.preventDefault();
    doSearch();
};
</script>

<template>
    <div class="flex-1 flex flex-col min-h-0 h-full">
        <div
            class="flex shadow-md z-2 shadow-gray-100 bg-white px-2 py-1 gap-1"
        >
            <form
                @submit="submit"
                class="flex-1 flex items-center rounded-full overflow-hidden bg-gray-100 relative"
            >
                <input
                    type="search"
                    placeholder="Search..."
                    name=""
                    id=""
                    class="h-9 flex-1 text-light bg-transparent text-black rounded-full px-4 w-full"
                    v-model="state.search"
                />
                <button
                    class="h-9 w-9 flex items-center justify-center text-app-500 absolute right-1"
                >
                    <i class="ri-search-line"></i>
                </button>
            </form>
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="state.filter_open = true"
            >
                <i class="ri-filter-line"></i>
            </button>
            <!-- <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('print')"
            >
                <i class="ri-printer-line"></i>
            </button> -->
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('excel')"
            >
                <i class="ri-file-excel-2-line"></i>
            </button>
        </div>
        <div class="flex-1 overflow-auto">
            <div
                class="min-h-full min-w-full flex flex-col bg-gray-50"
                v-if="state.data.length"
            >
                <div
                    class="rounded-none bg-white border-b-2"
                    v-for="item in state.data"
                    :key="item.id"
                >
                    <router-link :to="'/details/' + item.id">
                        <!-- @click="state.selected = item.id" -->
                        <div
                            class="flex justify-between px-4 pt-2 items-center"
                        >
                            <div class="flex gap-2 items-center h-6">
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="item.client_po_number !== null"
                                    >#PO {{ item.client_po_number }}</span
                                >
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="item.client_po_number === null"
                                    >#PO Dalam Proses</span
                                >
                                <i
                                    v-show="item.client_po_number === null"
                                    class="ri-error-warning-fill text-lg text-app-500"
                                ></i>
                            </div>
                            <div class="flex gap-2 items-center h-7">
                                <span class="text-xs text-black"
                                    >#JOB {{ item.job_number }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex justify-between gap-1 px-4 items-start"
                        >
                            <div class="flex w-full flex-col">
                                <div class="flex items-center flex-wrap my-6px">
                                    <span
                                        class="font-medium text-gray-400 text-xs leading-4 pr-2 w-full"
                                        >{{ item.title }}</span
                                    >
                                    <span
                                        class="font-semibold text-black text-sm leading-4 pr-2"
                                        >{{ item.products_data[0]?.name }}</span
                                    >
                                    <span
                                        class="text-blue-gray-500 text-xs"
                                        v-if="item.products_data.length > 1"
                                        >+{{
                                            item.products_data.length - 1
                                        }}
                                        other(s)</span
                                    >
                                </div>
                                <span class="text-app-900 text-xs text-left"
                                    >by {{ item.client_company }}</span
                                >
                            </div>
                            <div
                                class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
                                :class="
                                    item?.status === 'delivered'
                                        ? 'bg-[#6FD669]'
                                        : item?.status === 'partial'
                                        ? 'bg-[#4DC8E3]'
                                        : item?.status === 'ready'
                                        ? 'bg-[#E5C100]'
                                        : item?.status === 'cancel'
                                        ? 'bg-[#E44545]'
                                        : item?.status === 'production'
                                        ? 'bg-[#3f51b5]'
                                        : item?.status === 'new'
                                        ? 'bg-[#AD58D4]'
                                        : ''
                                "
                            >
                                {{ item?.status }}
                            </div>
                        </div>
                        <div class="border-b w-full mt-2"></div>
                        <div class="px-4 py-2 flex">
                            <div class="flex w-full">
                                <div class="flex-1 flex flex-col items-start">
                                    <span class="text-xs text-blue-gray-500"
                                        >Stored</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(item.stored ?? 0)
                                        }}</strong
                                        >/<span>{{ nom(item.total) }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-center">
                                    <span class="text-xs text-blue-gray-500"
                                        >Delivered</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(item.delivered ?? 0)
                                        }}</strong
                                        >/<span>{{ nom(item.total) }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-end">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoice</span
                                    >
                                    <div
                                        class="text-xs font-medium capitalize"
                                        :class="
                                            item?.invoice_status == 'sent'
                                                ? 'text-green-500'
                                                : 'text-orange-500'
                                        "
                                    >
                                        <span>{{
                                            item
                                                ?.invoice_status ?? 'Not Yet Processed'
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>
            </div>
            <div
                class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                v-else
            >
                <i class="ri-inbox-line text-100px text-gray-200"></i>
                <span class="text-sm font-medium text-gray-400"
                    >No Project Available</span
                >
            </div>
        </div>
        <div
            class="h-40px bg-gray-100 shadow-lg w-full border-t flex items-center px-1"
        >
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page <= 1
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="prevPage"
            >
                <i class="ri-skip-left-line"></i>
                <span class="text-xs">Prev</span>
            </div>
            <div class="flex-1 items-center justify-center flex text-sm">
                Page {{ state.page }} of {{ state.meta?.total_pages || 1 }}
            </div>
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page >= state.meta?.total_pages
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="nextPage"
            >
                <span class="text-xs">Next</span>
                <i class="ri-skip-right-line"></i>
            </div>
        </div>
    </div>

    <Bottomsheet
        :show="state.selected != null"
        :selectedProject="state.selected"
        @hide="state.selected = null"
    ></Bottomsheet>

    <BottomsheetDeliveryFilter
        :show="state.filter_open"
        :status="state.status"
        :po="state.po"
        :start_date="state.start_date"
        :end_date="state.end_date"
        :limit="state.limit"
        :client="state.client"
        @hide="state.filter_open = false"
        @apply="handleFilter"
        @clear="handleClearFilter"
    ></BottomsheetDeliveryFilter>
    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
