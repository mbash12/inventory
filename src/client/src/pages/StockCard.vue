<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useRoute } from 'vue-router';
import { currentUser, getStockCards, getStockInOptions, nom } from '../services/service';
import { loading } from '../services/router';
import StockSearchBar from '../components/StockSearchBar.vue';
import StockFilterSheet from '../components/StockFilterSheet.vue';
import StockPager from '../components/StockPager.vue';
import StockEmpty from '../components/StockEmpty.vue';

const route = useRoute();
const canWrite = computed(() => ['admin', 'delivery'].includes(currentUser.user?.user?.position));
const state = reactive({ search: '', scope: '', project_id: route.query.project_id || '', warehouse_id: '', page: 1, rows: [], meta: {}, options: { projects: [], warehouses: [] }, error: '', busy: false, filter_open: false });
const filters = () => Object.fromEntries(Object.entries({ search: state.search, scope: state.scope, project_id: state.project_id, warehouse_id: state.warehouse_id, page: state.page }).filter(([, value]) => value !== ''));
const load = async (reset = false) => {
    if (reset) state.page = 1;
    state.error = '';
    state.busy = true;
    loading();
    try {
        const result = await getStockCards(filters());
        state.rows = result.data;
        state.meta = result.meta;
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        loading(false);
    }
};
const filterFields = computed(() => [
    { key: 'scope', label: 'Barang', options: [{ value: '', label: 'Semua barang' }, { value: 'manual', label: 'Barang manual' }, { value: 'project', label: 'Barang project' }] },
    { key: 'warehouse_id', label: 'Gudang', options: [{ value: '', label: 'Semua gudang' }, ...state.options.warehouses.map((item) => ({ value: item.id, label: item.name }))] },
    ...(state.scope === 'manual' ? [] : [{ key: 'project_id', label: 'Project', options: [{ value: '', label: 'Semua project' }, ...state.options.projects.map((item) => ({ value: item.id, label: `${item.job_number} / ${item.client_po_number || 'Tanpa PO'}` }))] }]),
]);
const filtered = computed(() => !!(state.scope || state.warehouse_id || state.project_id));
const applyFilter = (values) => {
    state.scope = values.scope;
    state.warehouse_id = values.warehouse_id;
    state.project_id = values.scope === 'manual' ? '' : values.project_id ?? '';
    state.filter_open = false;
    load(true);
};
const cardLink = (item) => ({ path: '/stock/detail', query: {
    warehouse_id: item.warehouse_id, name: item.name, warehouse_name: item.warehouse_name, unit: item.unit,
    ...(item.type === 'product' ? { product_id: item.item_id } : { manual_item_id: item.item_id }),
} });
onMounted(async () => {
    try {
        state.options = (await getStockInOptions()).data;
        await load();
    } catch (error) {
        state.error = error.message;
    }
});
</script>

<template>
    <div class="flex-1 flex flex-col min-h-0 h-full text-black">
        <StockSearchBar v-model="state.search" placeholder="Cari barang / JOB..." :filtered="filtered" @search="load(true)" @filter="state.filter_open = true">
            <router-link class="h-9 w-9 flex items-center justify-center text-app-500" :to="{ path: '/stock/ins', query: state.project_id ? { project_id: state.project_id } : {} }">
                <i class="ri-file-list-3-line"></i>
            </router-link>
            <router-link v-if="canWrite" class="h-9 w-9 flex items-center justify-center text-app-500" :to="'/stock/items'">
                <i class="ri-archive-line"></i>
            </router-link>
        </StockSearchBar>
        <p v-if="state.error" role="alert" class="px-4 py-2 text-red-600 text-xs">{{ state.error }}</p>
        <div class="flex-1 overflow-auto">
            <div v-if="state.rows.length" class="min-h-full min-w-full flex flex-col bg-gray-50 gap-1">
                <router-link v-for="item in state.rows" :key="item.type + item.item_id + '-' + item.warehouse_id + '-' + item.project_id" :to="cardLink(item)">
                    <div class="rounded-none bg-white border-b">
                        <div class="flex justify-between px-4 pt-2 items-center -mb-1">
                            <span class="text-xs text-blue-gray-500 font-semibold h-6 flex items-center">{{ item.job_number ? '#JOB ' + item.job_number : 'Manual / tanpa project' }}</span>
                            <span class="text-xs text-app-500">Stock card</span>
                        </div>
                        <div class="flex gap-1 px-4 py-1 pt-0 pb-3 justify-start items-center">
                            <div class="flex-1 flex flex-col items-start">
                                <span class="font-semibold text-black text-sm text-left">{{ item.name }}</span>
                                <span class="text-app-900 text-xs">{{ item.warehouse_name }}</span>
                            </div>
                            <div class="flex my-1 text-black text-sm">{{ nom(item.quantity, 0) }} {{ item.unit }}</div>
                        </div>
                    </div>
                </router-link>
            </div>
            <StockEmpty v-else-if="!state.busy" text="No Stock Available" />
        </div>
        <div v-if="canWrite" class="px-4 py-2 flex items-center justify-center w-full gap-2 border-t">
            <router-link class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full" :to="{ path: '/stock/ins/add', query: state.project_id ? { project_id: state.project_id } : {} }">
                <i class="ri-add-line text-xl"></i>
                <span class="text-sm mt-1"> Stock In </span>
            </router-link>
            <router-link class="flex px-3 h-12 gap-2 items-center justify-center border border-app-500 text-app-500 rounded-full w-full" :to="{ path: '/stock/ins/add', query: { direction: 'out' } }">
                <i class="ri-subtract-line text-xl"></i>
                <span class="text-sm mt-1"> Stock Out </span>
            </router-link>
        </div>
        <StockPager :page="state.page" :total="state.meta.total_pages" @prev="state.page--; load()" @next="state.page++; load()" />
    </div>
    <StockFilterSheet :show="state.filter_open" :fields="filterFields" :values="{ scope: state.scope, warehouse_id: state.warehouse_id, project_id: state.project_id }" @hide="state.filter_open = false" @apply="applyFilter" />
</template>
