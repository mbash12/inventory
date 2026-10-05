<script setup>
import { computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Confirm from '../components/Confirm.vue';
import { ASSETSURL, currentUser, deleteStockIn, getStockIn, nom } from '../services/service';
import { loading } from '../services/router';

const route = useRoute();
const router = useRouter();
const canWrite = computed(() => ['admin', 'delivery'].includes(currentUser.user?.user?.position));
const labels = { in: 'Stock In', out: 'Stock Out' };
const state = reactive({ doc: null, busy: false, error: '', action: '' });
const files = computed(() => JSON.parse(state.doc?.files || '[]'));
const itemName = (item) => item.product_data?.name ?? item.manual_item_data?.name;
const itemUnit = (item) => item.manual_item_data?.unit ?? 'pcs';
const load = async () => {
    state.error = '';
    loading();
    try {
        state.doc = (await getStockIn(route.params.id)).data;
    } catch (error) {
        state.error = error.message;
    } finally {
        loading(false);
    }
};
const cardLink = (item) => ({ path: '/desktop/stock/detail', query: {
    warehouse_id: state.doc.warehouse, name: itemName(item), warehouse_name: state.doc.warehouse_data?.name, unit: itemUnit(item),
    ...(item.product ? { product_id: item.product } : { manual_item_id: item.manual_item }),
} });
const execute = async () => {
    if (state.busy) return;
    const { action } = state;
    state.action = '';
    state.busy = true;
    state.error = '';
    try {
        if (action === 'delete') {
            await deleteStockIn(state.doc.id);
            await router.replace('/desktop/stock/ins');
        }
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
    }
};
watch(() => route.params.id, load, { immediate: true });
</script>

<template>
    <div class="p-8 max-w-4xl">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">Detail Dokumen Stok</div>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <template v-if="state.doc">
            <div class="bg-white rounded-lg shadow p-6 mb-4 text-left">
                <div class="flex justify-between items-center"><span class="text-lg font-semibold break-all"># {{ state.doc.do_number }}</span></div>
                <div class="text-sm text-gray-500 mt-1">{{ labels[state.doc.direction] }} · {{ String(state.doc.document_date).slice(0, 10) }}</div>
                <div class="text-sm mt-1"><template v-if="state.doc.origin_data"><span class="text-gray-500">{{ state.doc.origin_data.name }} to </span></template>{{ state.doc.warehouse_data?.name }}</div>
                <div class="text-sm text-gray-500 mt-1">{{ state.doc.project_data ? '#JOB ' + state.doc.project_data.job_number : 'Manual / tanpa project' }}</div>
                <p v-if="state.doc.notes" class="text-sm mt-2 whitespace-pre-wrap">{{ state.doc.notes }}</p>
                <div v-if="files.length" class="mt-2 flex gap-4"><a v-for="(file, index) in files" :key="file + index" :href="ASSETSURL + file" target="_blank" rel="noopener noreferrer" class="text-[#306DCE] text-sm">Lampiran {{ index + 1 }}</a></div>
            </div>
            <div class="bg-white rounded-lg shadow overflow-hidden mb-4">
                <table class="w-full text-left">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px"><tr><th class="p-3 pl-4 font-bold">Barang</th><th class="p-3 font-bold text-right">Qty</th><th class="p-3 font-bold">Tanggal</th></tr></thead>
                    <tbody class="text-13px">
                        <tr v-for="item in state.doc.items" :key="item.id" class="border-b h-56px">
                            <td class="p-3 pl-4">{{ itemName(item) }}<div><router-link :to="cardLink(item)" class="text-xs text-[#306DCE]">Stock card</router-link></div></td>
                            <td class="p-3 text-right">{{ nom(item.actual_quantity, 0) }} {{ itemUnit(item) }}</td>
                            <td class="p-3 text-green-600">{{ (state.doc.direction === 'in' ? 'Diterima ' : 'Dikeluarkan ') + String(item.received_at).slice(0, 10) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex gap-3">
                <button class="h-45px px-4 border rounded-lg" @click="router.push('/desktop/stock/ins')">Kembali</button>
                <template v-if="canWrite">
                    <router-link class="h-45px px-4 border border-red-300 text-red-600 rounded-lg flex items-center" :to="'/desktop/stock/ins/' + state.doc.id + '/edit'">Ubah</router-link>
                    <button class="h-45px px-4 border border-red-300 text-red-600 rounded-lg" :disabled="state.busy" @click="state.action = 'delete'">Hapus</button>
                </template>
            </div>
        </template>
        <Confirm :show="state.action === 'delete'" type="fail" title="Hapus dokumen" content="Hapus dokumen ini? Stok yang tercatat dari dokumen ini akan dikembalikan." buttonText="Hapus" @hide="state.action = ''" @fire="execute" />
    </div>
</template>
