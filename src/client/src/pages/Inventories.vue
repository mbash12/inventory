<script setup>
import { reactive, onMounted } from "vue";
import { getInventoryList, getWarehouseList, nom } from "../services/service";
import { loading } from "../services/router";
import BottomsheetWarehouseFilter from "../components/BottomsheetWarehouseFilter.vue";
import Table from "../components/Table.vue";
import { useRoute } from "vue-router";
const state = reactive({
    search: "",
    warehouses: [],
    filter_open: false,
    data: null,
    warehouse: [],
    page: 1,
    limit: 10,
    export: null,
    export_mode: "print",
});
const route = useRoute();
const handleFilter = (e) => {
    state.page = 1;
    state.warehouse = e.warehouse;
    state.filter_open = false;
    state.limit = e.limit;
    searchh();
};

const prevPage = () => {
    if (state.meta) {
        if (state.page - 1 >= 1) {
            state.page = state.page - 1;
            searchh();
        }
    }
};
const nextPage = () => {
    if (state.meta) {
        if (state.page + 1 <= state.meta?.total_pages) {
            state.page = state.page + 1;
            searchh();
        }
    }
};
const doSearch = () => {
    state.page = 1;
    searchh();
};
const searchh = () => {
    loading();
    let filter = {
        search: state.search,
        warehouse: state.warehouse.join(","),
        page: state.page,
        limit: state.limit,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem("inv", JSON.stringify(filter));

    getInventoryList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }
    });
};

const prepareExport = (mode) => {
    state.export_mode = mode;
    let data = state.data.map((e) => ({
        Warehouse: e.warehouse_data.name,
        Product: e.product_data.name,
        Quantity: nom(e.quantity),
        Client: e.project_data.client_company,
        "Client PIC": e.project_data.client_pic_name,
        "Client PIC": e.project_data.client_pic_name,
        "PO Number": e.project_data.client_po_number ?? "Dalam Proses",
        "Job Number": e.project_data.job_number,
    }));

    state.export = data;
};

const submit = (e) => {
    e.preventDefault();
    doSearch();
};
onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("inv");
        if (flt) {
            flt = JSON.parse(flt);
            state.filter_open = flt?.filter_open ?? null;
            state.search = flt?.search ?? null;
            state.selected = flt?.selected ?? null;
            state.order_by = flt?.order_by ?? null;
            state.sort = flt?.sort ?? null;
            state.warehouse = flt?.warehouse?.split(",") ?? [];
            state.po = flt?.po ?? null;
            state.page = flt?.page ?? null;
            state.limit = flt?.limit ?? null;
        }
    } else {
        localStorage.removeItem("inv");
    }
    getWarehouseList().then((r) => {
        if (r.code === 200) {
            state.warehouses = r.data;
        }
    });
    searchh();
});
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
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('print')"
            >
                <i class="ri-printer-line"></i>
            </button>
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('excel')"
            >
                <i class="ri-file-excel-2-line"></i>
            </button>
        </div>
        <div class="flex-1 overflow-auto">
            <div
                class="min-h-full min-w-full flex flex-col bg-gray-50 gap-1"
                v-if="state.data?.length"
            >
                <div v-for="item in state.data" :key="item.id">
                    <router-link :to="'/inventory/' + item.id">
                        <div class="rounded-none bg-white border-b">
                            <div
                                class="flex justify-between px-4 pt-2 items-center -mb-1"
                            >
                                <div class="flex gap-1 items-center h-6">
                                    <span
                                        class="text-xs text-blue-gray-500 font-semibold"
                                        v-show="
                                            item.project_data
                                                .client_po_number !== null
                                        "
                                        >#PO
                                        {{
                                            item.project_data.client_po_number
                                        }}</span
                                    >
                                    <span
                                        class="text-xs text-blue-gray-500 font-semibold"
                                        v-show="
                                            item.project_data
                                                .client_po_number === null
                                        "
                                        >#PO Dalam Proses</span
                                    >
                                    <i
                                        v-show="
                                            item.project_data
                                                .client_po_number === null
                                        "
                                        class="ri-error-warning-fill text-lg text-app-500"
                                    ></i>
                                </div>
                                <div class="flex gap-2 items-center h-7">
                                    <span class="text-xs text-black"
                                        >#JOB
                                        {{ item.project_data.job_number }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex gap-1 px-4 py-1 pt-0 pb-1 justify-start items-center"
                            >
                                <div class="flex-1 flex flex-col items-start">
                                    <span
                                        class="font-semibold text-black text-sm"
                                        >{{ item.product_data.name }}</span
                                    >
                                    <span class="text-app-900 text-xs">{{
                                        item.warehouse_data.name
                                    }}</span>
                                </div>
                                <div class="flex my-1 text-black text-sm">
                                    {{ nom(item.quantity) }}
                                </div>
                            </div>
                            <div
                                class="flex gap-1 px-4 py-1 pb-3 justify-between gap-4 items-start border-t"
                            >
                                <span class="text-app-900 text-xs text-left">{{
                                    item.project_data?.client_company
                                }}</span>
                                <span
                                    class="text-xs text-gray-500 text-left flex-shrink-0"
                                >
                                    {{ item.project_data?.client_pic_name }}
                                </span>
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
                    >No Inventory Available</span
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

    <BottomsheetWarehouseFilter
        :show="state.filter_open"
        :warehouse="state.warehouse"
        :warehouses="state.warehouses"
        :limit="state.limit"
        @hide="state.filter_open = false"
        @apply="handleFilter"
    ></BottomsheetWarehouseFilter>

    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
