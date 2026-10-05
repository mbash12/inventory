<script setup>
import { onMounted, reactive } from 'vue';
import { useRoute } from 'vue-router';
import Navbar from '../components/Navbar.vue';
import { getStockCardDetail, nom } from '../services/service';
import { loading } from '../services/router';

const route = useRoute();
const kindText = { stock_in: 'Stock In', stock_out: 'Stock Out', transfer_out: 'Transfer Keluar', delivery: 'Delivery' };
const state = reactive({ from: '', to: '', card: null, busy: false, error: '' });
const load = async () => {
    state.error = '';
    state.busy = true;
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
        state.busy = false;
        loading(false);
    }
};
const link = (row) => (row.stock_in_id ? '/stock/ins/' + row.stock_in_id : '/delivery/details/' + row.delivery_id);
onMounted(load);
</script>

<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Stock Card" :back="'/stock'" />
        <div class="px-4 py-2 border-b flex flex-col items-start">
            <span class="font-semibold text-black text-sm text-left">{{ route.query.name || 'Kartu stok barang' }}</span>
            <span class="text-app-900 text-xs">{{ route.query.warehouse_name }} · {{ route.query.product_id ? 'Produk project' : 'Barang manual' }}</span>
        </div>
        <form class="flex items-end py-2 border-b" @submit.prevent="load">
            <label class="flex flex-col items-start gap-1 px-4 w-full"><span class="text-xs text-blue-gray-400">Dari</span><input v-model="state.from" type="date" class="border-b w-full h-10 bg-transparent text-sm text-black" /></label>
            <label class="flex flex-col items-start gap-1 px-4 w-full"><span class="text-xs text-blue-gray-400">Sampai</span><input v-model="state.to" type="date" :min="state.from || undefined" class="border-b w-full h-10 bg-transparent text-sm text-black" /></label>
            <button class="h-9 w-9 mr-2 flex-shrink-0 flex items-center justify-center text-app-500" :disabled="state.busy"><i class="ri-search-line"></i></button>
        </form>
        <p v-if="state.error" role="alert" class="px-4 py-2 text-red-600 text-xs">{{ state.error }}</p>
        <div v-if="state.card" class="flex-1 overflow-auto bg-gray-50">
            <div class="px-4 py-2 flex justify-between text-sm bg-white border-b"><span class="text-blue-gray-500">Saldo awal periode</span><strong>{{ nom(state.card.opening_balance, 0) }}</strong></div>
            <div class="overflow-x-auto bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-100 text-blue-gray-500"><tr><th class="px-4 py-2 font-semibold">Tanggal / Dokumen</th><th class="p-2 text-right font-semibold">Masuk</th><th class="p-2 text-right font-semibold">Keluar</th><th class="px-4 py-2 text-right font-semibold">Saldo</th></tr></thead>
                    <tbody>
                        <tr v-for="(row, index) in state.card.rows" :key="index" class="border-b">
                            <td class="px-4 py-2">
                                <div>{{ row.date }} · {{ kindText[row.kind] }}</div>
                                <router-link :to="link(row)" class="text-app-500 break-all">{{ row.number }}</router-link>
                                <div v-if="row.notes" class="text-gray-500">{{ row.notes }}</div>
                            </td>
                            <td class="p-2 text-right text-green-600">{{ nom(row.in, 0) }}</td>
                            <td class="p-2 text-right text-red-600">{{ nom(row.out, 0) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ nom(row.balance, 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!state.card.rows.length" class="p-6 text-center text-sm font-medium text-gray-400">Tidak ada mutasi pada periode ini.</p>
            <div class="px-4 py-2 flex justify-between text-sm bg-white border-b"><span class="text-blue-gray-500">Saldo akhir periode</span><strong>{{ nom(state.card.closing_balance, 0) }} {{ route.query.unit || 'pcs' }}</strong></div>
        </div>
    </main>
</template>
