<script setup>
import { computed, onMounted, reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import { currentUser, getStockCards, getStockInOptions, nom } from "../services/service";
import { loading } from "../services/router";
import MktFilterPopover from "../components/MktFilterPopover.vue";
import MktPager from "../components/MktPager.vue";
import Table from "../components/Table.vue";

const route = useRoute();
const router = useRouter();
const canWrite = computed(() => ["admin", "delivery"].includes(currentUser.user?.user?.position));
const state = reactive({ search: "", scope: "", project_id: route.query.project_id || "", warehouse_id: "", page: 1, rows: [], meta: {}, options: { projects: [], warehouses: [] }, error: "", export: null, export_mode: "print" });
const filterFields = computed(() => [
    { key: "scope", label: "Barang", options: [{ value: "", label: "Semua barang" }, { value: "manual", label: "Barang manual" }, { value: "project", label: "Barang project" }] },
    { key: "warehouse_id", label: "Gudang", options: [{ value: "", label: "Semua gudang" }, ...state.options.warehouses.map((item) => ({ value: item.id, label: item.name }))] },
    { key: "project_id", label: "Project", options: [{ value: "", label: "Semua project" }, ...state.options.projects.map((item) => ({ value: item.id, label: `${item.job_number} / ${item.client_po_number || "Tanpa PO"}` }))] },
]);
const filtered = computed(() => !!(state.scope || state.warehouse_id || state.project_id));
const load = async (reset = false) => {
    if (reset) state.page = 1;
    state.error = "";
    loading();
    try {
        const params = Object.fromEntries(Object.entries({ search: state.search, scope: state.scope, project_id: state.project_id, warehouse_id: state.warehouse_id, page: state.page }).filter(([, value]) => value !== ""));
        const result = await getStockCards(params);
        state.rows = result.data;
        state.meta = result.meta;
    } catch (error) {
        state.error = error.message;
    } finally {
        loading(false);
    }
};
const applyFilter = (values) => {
    state.scope = values.scope;
    state.warehouse_id = values.warehouse_id;
    state.project_id = values.scope === "manual" ? "" : values.project_id;
    load(true);
};
const resetFilter = () => {
    state.search = "";
    state.scope = "";
    state.warehouse_id = "";
    state.project_id = "";
    load(true);
};
const cardLink = (item) => ({
    path: "/desktop/stock/detail",
    query: { warehouse_id: item.warehouse_id, name: item.name, warehouse_name: item.warehouse_name, unit: item.unit, ...(item.type === "product" ? { product_id: item.item_id } : { manual_item_id: item.item_id }) },
});
const prepareExport = (mode) => {
    state.export_mode = mode;
    state.export = state.rows.map((row) => ({ Product: row.name, Source: row.job_number ? "JOB " + row.job_number : "Manual / tanpa project", Warehouse: row.warehouse_name, Quantity: nom(row.quantity, 0), Unit: row.unit }));
};
onMounted(async () => {
    try {
        state.options = (await getStockInOptions()).data;
    } catch (error) {
        state.error = error.message;
    }
    load();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">Stock Card</div>
        <div class="flex flex-wrap justify-between gap-2 mb-6">
            <div class="flex flex-wrap gap-2">
                <MktFilterPopover :fields="filterFields" :values="{ scope: state.scope, warehouse_id: state.warehouse_id, project_id: state.project_id }" :active="filtered" @apply="applyFilter" @reset="resetFilter" />
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center text-[#306DCE]" @click="prepareExport('print')"><i class="ri-printer-fill text-xl"></i><strong>Print</strong></button>
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center text-[#1C8B54]" @click="prepareExport('excel')"><i class="ri-file-excel-2-fill text-xl"></i><strong>Excel</strong></button>
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center" @click="router.push('/desktop/stock/ins')"><i class="ri-file-list-3-fill text-xl"></i><strong>Dokumen</strong></button>
                <button v-if="canWrite" class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center" @click="router.push('/desktop/stock/items')"><i class="ri-archive-fill text-xl"></i><strong>Barang Manual</strong></button>
                <button v-if="canWrite" class="h-45px px-4 whitespace-nowrap border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:bg-red-600 flex items-center" @click="router.push({ path: '/desktop/stock/ins/add', query: state.project_id ? { project_id: state.project_id } : {} })"><i class="ri-add-line text-xl"></i><strong>Stock In</strong></button>
                <button v-if="canWrite" class="h-45px px-4 whitespace-nowrap border border-red-300 bg-white rounded-lg gap-2 shadow text-red-600 hover:bg-gray-50 flex items-center" @click="router.push({ path: '/desktop/stock/ins/add', query: { direction: 'out' } })"><i class="ri-subtract-line text-xl"></i><strong>Stock Out</strong></button>
            </div>
            <form class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300" @submit.prevent="load(true)">
                <input type="text" class="h-full w-full rounded-full bg-transparent pl-45px" placeholder="Search barang / JOB" v-model="state.search" />
                <i class="ri-search-line absolute left-4 text-xl text-[#667085]"></i>
            </form>
        </div>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full h-full border-b">
                <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                    <tr class="text-left"><th class="p-3 pl-4 font-bold">Product</th><th class="p-3 font-bold">Source</th><th class="p-3 font-bold">Warehouse</th><th class="p-3 font-bold">Quantity</th><th class="p-3 font-bold w-60px"></th></tr>
                </thead>
                <tbody class="text-13px">
                    <tr v-for="row in state.rows" :key="row.type + row.item_id + '-' + row.warehouse_id + '-' + row.project_id" class="border-b h-56px text-left">
                        <td class="p-3 pl-4">{{ row.name }}</td>
                        <td class="p-3">{{ row.job_number ? "JOB " + row.job_number : "Manual / tanpa project" }}</td>
                        <td class="p-3">{{ row.warehouse_name }}</td>
                        <td class="p-3">{{ nom(row.quantity, 0) }} {{ row.unit }}</td>
                        <td class="p-3"><router-link :to="cardLink(row)" title="Stock Card"><div class="text-xl px-3 py-1 text-[#667085] hover:bg-gray-100 rounded"><i class="ri-file-list-2-line"></i></div></router-link></td>
                    </tr>
                    <tr v-if="!state.rows.length"><td colspan="5" class="p-8 text-center text-sm font-medium text-gray-400">No Stock Available</td></tr>
                </tbody>
            </table>
            <MktPager :page="state.page" :total="state.meta.total_pages" @prev="state.page--; load()" @next="state.page++; load()" />
        </div>
    </div>
    <Table :data="state.export" :action="state.export_mode" @close="state.export = null" />
</template>
