<script setup>
import { computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Navbar from '../components/Navbar.vue';
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
const cardLink = (item) => ({ path: '/stock/detail', query: {
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
            await router.replace('/stock/ins');
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
    <main class="flex flex-col h-full text-black">
        <Navbar title="Detail Dokumen Stok" :back="'/stock/ins'" />
        <div class="flex-1 overflow-auto bg-gray-50 flex flex-col gap-1">
            <p v-if="state.error" role="alert" class="px-4 py-2 text-sm text-red-600">{{ state.error }}</p>
            <template v-if="state.doc">
                <div class="bg-white border-b px-4 py-2 flex flex-col items-start gap-1">
                    <div class="flex justify-between items-center w-full">
                        <span class="font-semibold text-black text-sm break-all text-left"># {{ state.doc.do_number }}</span>
                    </div>
                    <span class="text-xs text-blue-gray-500">{{ labels[state.doc.direction] }} · {{ String(state.doc.document_date).slice(0, 10) }}</span>
                    <div class="text-xs">
                        <template v-if="state.doc.origin_data"><span class="text-gray-500">{{ state.doc.origin_data.name }} to </span></template>{{ state.doc.warehouse_data?.name }}
                    </div>
                    <span class="text-xs text-gray-500">{{ state.doc.project_data ? '#JOB ' + state.doc.project_data.job_number : 'Manual / tanpa project' }}</span>
                    <p v-if="state.doc.notes" class="text-xs whitespace-pre-wrap text-left">{{ state.doc.notes }}</p>
                </div>
                <div v-for="item in state.doc.items" :key="item.id" class="bg-white border-b px-4 py-2 flex flex-col gap-1">
                    <div class="flex justify-between text-sm gap-3">
                        <span class="font-semibold text-left">{{ itemName(item) }}</span>
                        <span>{{ nom(item.actual_quantity, 0) }} {{ itemUnit(item) }}</span>
                    </div>
                    <span class="text-xs text-green-600 text-left">{{ state.doc.direction === 'in' ? 'Diterima' : 'Dikeluarkan' }} {{ String(item.received_at).slice(0, 10) }}</span>
                    <router-link :to="cardLink(item)" class="text-app-500 text-xs text-left">Stock card gudang {{ state.doc.warehouse_data?.name }}</router-link>
                </div>
                <div v-if="files.length" class="bg-white border-b px-4 py-2 flex flex-col items-start gap-1">
                    <a v-for="(file, index) in files" :key="file + index" :href="ASSETSURL + file" target="_blank" rel="noopener noreferrer" class="text-app-500 text-xs">Lampiran {{ index + 1 }}</a>
                </div>
            </template>
        </div>
        <div v-if="state.doc && canWrite" class="px-4 py-2 flex flex-col gap-2 w-full border-t">
            <div class="flex gap-2">
                <router-link class="flex px-3 h-12 items-center justify-center border border-app-500 text-app-500 rounded-full flex-1" :to="'/stock/ins/' + state.doc.id + '/edit'"><span class="text-sm mt-1">Ubah</span></router-link>
                <button class="flex px-3 h-12 items-center justify-center border border-red-300 text-red-600 rounded-full flex-1" :disabled="state.busy" @click="state.action = 'delete'"><span class="text-sm mt-1">Hapus</span></button>
            </div>
        </div>
        <Confirm :show="state.action === 'delete'" type="fail" title="Hapus dokumen" content="Hapus dokumen ini? Stok yang tercatat dari dokumen ini akan dikembalikan." buttonText="Hapus" @hide="state.action = ''" @fire="execute" />
    </main>
</template>
