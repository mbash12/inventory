<script setup>
import { onMounted, reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getStockCardDetail, nom } from "../services/service";
import { loading } from "../services/router";
import Table from "../components/Table.vue";

const route = useRoute();
const router = useRouter();
const kindText = { stock_in: "Stock In", stock_out: "Stock Out", transfer_out: "Transfer Keluar", delivery: "Delivery" };
const state = reactive({ from: "", to: "", card: null, error: "", export: null, export_mode: "print" });
const load = async () => {
    state.error = "";
    loading();
    try {
        const params = { warehouse_id: route.query.warehouse_id };
        if (route.query.product_id) params.product_id = route.query.product_id;
        if (route.query.manual_item_id) params.manual_item_id = route.query.manual_item_id;
        if (state.from) params.from = state.from;
        if (state.to) params.to = state.to;
        state.card = (await getStockCardDetail(params)).data;
    } catch (error) {
        state.error = error.message;
    } finally {
        loading(false);
    }
};
const link = (row) => (row.stock_in_id ? "/desktop/stock/ins/" + row.stock_in_id : "/desktop/delivery");
const prepareExport = (mode) => {
    state.export_mode = mode;
    state.export = state.card.rows.map((row) => ({ Date: row.date, Type: kindText[row.kind], Document: row.number, Note: row.notes || "", In: nom(row.in, 0), Out: nom(row.out, 0), Balance: nom(row.balance, 0) }));
};
onMounted(load);
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-1 text-left">Stock Card</div>
        <div class="text-sm text-gray-500 mb-6 text-left">{{ route.query.name || "Kartu stok barang" }} · {{ route.query.warehouse_name }} · {{ route.query.product_id ? "Produk project" : "Barang manual" }}</div>
        <div class="flex flex-wrap justify-between gap-2 mb-6">
            <div class="flex flex-wrap gap-2">
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center" @click="router.push('/desktop/stock')"><i class="ri-arrow-left-line text-xl"></i><strong>Stock Card</strong></button>
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center text-[#306DCE] disabled:opacity-50" :disabled="!state.card" @click="prepareExport('print')"><i class="ri-printer-fill text-xl"></i><strong>Print</strong></button>
                <button class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center text-[#1C8B54] disabled:opacity-50" :disabled="!state.card" @click="prepareExport('excel')"><i class="ri-file-excel-2-fill text-xl"></i><strong>Excel</strong></button>
            </div>
            <form class="flex items-center gap-2" @submit.prevent="load">
                <label class="flex items-center gap-2 text-sm">Dari <input v-model="state.from" type="date" class="border rounded-lg bg-white h-45px px-3 text-14px" /></label>
                <label class="flex items-center gap-2 text-sm">Sampai <input v-model="state.to" type="date" :min="state.from || undefined" class="border rounded-lg bg-white h-45px px-3 text-14px" /></label>
                <button class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:bg-red-600 flex items-center"><i class="ri-search-line text-xl"></i><strong>Filter</strong></button>
            </form>
        </div>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <div v-if="state.card" class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 flex justify-between text-sm border-b"><span class="text-[#8A92A6]">Saldo awal periode</span><strong>{{ nom(state.card.opening_balance, 0) }}</strong></div>
            <table class="w-full">
                <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                    <tr class="text-left"><th class="p-3 pl-4 font-bold">Tanggal</th><th class="p-3 font-bold">Jenis</th><th class="p-3 font-bold">Dokumen</th><th class="p-3 font-bold">Keterangan</th><th class="p-3 font-bold text-right">Masuk</th><th class="p-3 font-bold text-right">Keluar</th><th class="p-3 pr-4 font-bold text-right">Saldo</th></tr>
                </thead>
                <tbody class="text-13px">
                    <tr v-for="(row, index) in state.card.rows" :key="index" class="border-b h-56px text-left">
                        <td class="p-3 pl-4">{{ row.date }}</td>
                        <td class="p-3">{{ kindText[row.kind] }}</td>
                        <td class="p-3"><router-link :to="link(row)" class="text-[#306DCE] break-all">{{ row.number }}</router-link></td>
                        <td class="p-3 text-gray-500">{{ row.notes }}</td>
                        <td class="p-3 text-right text-green-600">{{ nom(row.in, 0) }}</td>
                        <td class="p-3 text-right text-red-600">{{ nom(row.out, 0) }}</td>
                        <td class="p-3 pr-4 text-right font-semibold">{{ nom(row.balance, 0) }}</td>
                    </tr>
                    <tr v-if="!state.card.rows.length"><td colspan="7" class="p-8 text-center text-sm font-medium text-gray-400">Tidak ada mutasi pada periode ini.</td></tr>
                </tbody>
            </table>
            <div class="px-4 py-3 flex justify-between text-sm"><span class="text-[#8A92A6]">Saldo akhir periode</span><strong>{{ nom(state.card.closing_balance, 0) }} {{ route.query.unit || "pcs" }}</strong></div>
        </div>
    </div>
    <Table :data="state.export" :action="state.export_mode" @close="state.export = null" />
</template>
