<script setup>
import { computed, onMounted, reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import { currentUser, getStockIns } from "../services/service";
import { loading } from "../services/router";
import MktFilterPopover from "../components/MktFilterPopover.vue";
import MktPager from "../components/MktPager.vue";

const route = useRoute();
const router = useRouter();
const canWrite = computed(() => ["admin", "delivery"].includes(currentUser.user?.user?.position));
const labels = { in: "Stock In", out: "Stock Out" };
const state = reactive({ rows: [], direction: "", search: "", project_id: route.query.project_id || "", page: 1, meta: {}, error: "" });
const filterFields = [
    { key: "direction", label: "Jenis", options: [{ value: "", label: "Semua jenis" }, ...Object.entries(labels).map(([value, label]) => ({ value, label }))] },
];
const filtered = computed(() => !!state.direction);
const load = async (reset = false) => {
    if (reset) state.page = 1;
    state.error = "";
    loading();
    try {
        const params = Object.fromEntries(Object.entries({ direction: state.direction, search: state.search, project_id: state.project_id, page: state.page }).filter(([, value]) => value !== ""));
        const result = await getStockIns(params);
        state.rows = result.data;
        state.meta = result.meta;
    } catch (error) {
        state.error = error.message;
    } finally {
        loading(false);
    }
};
const applyFilter = (values) => {
    state.direction = values.direction;
    load(true);
};
const resetFilter = () => {
    state.direction = "";
    state.search = "";
    load(true);
};
onMounted(() => load());
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">Dokumen Stock In / Out</div>
        <div class="flex flex-wrap justify-between gap-2 mb-6">
            <div class="flex flex-wrap gap-2">
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center" @click="router.push('/desktop/stock')"><i class="ri-arrow-left-line text-xl"></i><strong>Stock Card</strong></button>
                <MktFilterPopover :fields="filterFields" :values="{ direction: state.direction }" :active="filtered" @apply="applyFilter" @reset="resetFilter" />
                <button v-if="canWrite" class="h-45px px-4 whitespace-nowrap border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:bg-red-600 flex items-center" @click="router.push('/desktop/stock/ins/add')"><i class="ri-add-line text-xl"></i><strong>Tambah Dokumen</strong></button>
            </div>
            <form class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300" @submit.prevent="load(true)">
                <input type="text" class="h-full w-full rounded-full bg-transparent pl-45px" placeholder="Nomor DO / JOB" v-model="state.search" />
                <i class="ri-search-line absolute left-4 text-xl text-[#667085]"></i>
            </form>
        </div>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full h-full border-b">
                <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                    <tr class="text-left"><th class="p-3 pl-4 font-bold">DO</th><th class="p-3 font-bold">Jenis</th><th class="p-3 font-bold">Tanggal</th><th class="p-3 font-bold">Sumber</th><th class="p-3 font-bold">Gudang</th><th class="p-3 font-bold">Barang</th></tr>
                </thead>
                <tbody class="text-13px">
                    <tr v-for="item in state.rows" :key="item.id" class="border-b h-56px text-left cursor-pointer hover:bg-gray-50" @click="router.push('/desktop/stock/ins/' + item.id)">
                        <td class="p-3 pl-4 font-semibold">{{ item.do_number }}</td>
                        <td class="p-3">{{ labels[item.direction] }}</td>
                        <td class="p-3">{{ String(item.document_date).slice(0, 10) }}</td>
                        <td class="p-3">{{ item.project_data ? "JOB " + item.project_data.job_number : "Manual / tanpa project" }}</td>
                        <td class="p-3">{{ item.warehouse_data?.name }}</td>
                        <td class="p-3">{{ item.items_count }} item</td>
                    </tr>
                    <tr v-if="!state.rows.length"><td colspan="6" class="p-8 text-center text-sm font-medium text-gray-400">No Document Available</td></tr>
                </tbody>
            </table>
            <MktPager :page="state.page" :total="state.meta.total_pages" @prev="state.page--; load()" @next="state.page++; load()" />
        </div>
    </div>
</template>
