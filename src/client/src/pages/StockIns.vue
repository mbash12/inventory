<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useRoute } from 'vue-router';
import Navbar from '../components/Navbar.vue';
import { currentUser, getStockIns } from '../services/service';
import { loading } from '../services/router';
import StockSearchBar from '../components/StockSearchBar.vue';
import StockFilterSheet from '../components/StockFilterSheet.vue';
import StockPager from '../components/StockPager.vue';
import StockEmpty from '../components/StockEmpty.vue';

const route = useRoute();
const canWrite = computed(() => ['admin', 'delivery'].includes(currentUser.user?.user?.position));
const labels = { in: 'Stock In', out: 'Stock Out' };
const state = reactive({ rows: [], direction: '', search: '', project_id: route.query.project_id || '', page: 1, meta: {}, error: '', busy: false, filter_open: false });
const load = async (reset = false) => {
    if (reset) state.page = 1;
    state.busy = true;
    state.error = '';
    loading();
    try {
        const params = Object.fromEntries(Object.entries({ direction: state.direction, search: state.search, project_id: state.project_id, page: state.page }).filter(([, value]) => value !== ''));
        const result = await getStockIns(params);
        state.rows = result.data;
        state.meta = result.meta;
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        loading(false);
    }
};
const filterFields = [
    { key: 'direction', label: 'Jenis', options: [{ value: '', label: 'Semua jenis' }, ...Object.entries(labels).map(([value, label]) => ({ value, label }))] },
];
const filtered = computed(() => !!state.direction);
const applyFilter = (values) => {
    state.direction = values.direction;
    state.filter_open = false;
    load(true);
};
onMounted(() => load());
</script>

<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Dokumen Stock In / Out" :back="'/stock'" />
        <StockSearchBar v-model="state.search" placeholder="Nomor DO / JOB..." :filtered="filtered" @search="load(true)" @filter="state.filter_open = true" />
        <p v-if="state.error" role="alert" class="px-4 py-2 text-red-600 text-xs">{{ state.error }}</p>
        <div class="flex-1 overflow-auto">
            <div v-if="state.rows.length" class="min-h-full min-w-full flex flex-col bg-gray-50 gap-1">
                <router-link v-for="item in state.rows" :key="item.id" :to="'/stock/ins/' + item.id">
                    <div class="rounded-none bg-white border-b px-4 py-2 flex flex-col items-start gap-1">
                        <div class="flex justify-between items-center w-full">
                            <span class="font-semibold text-black text-sm break-all text-left"># {{ item.do_number }}</span>
                        </div>
                        <span class="text-xs text-blue-gray-500">{{ labels[item.direction] }} · {{ item.document_date }}</span>
                        <div class="text-xs">
                            <template v-if="item.origin_data"><span class="text-gray-500">{{ item.origin_data.name }} to </span></template>{{ item.warehouse_data?.name }}
                        </div>
                        <span class="text-xs text-gray-500">{{ item.project_data ? '#JOB ' + item.project_data.job_number : 'Manual / tanpa project' }}</span>
                    </div>
                </router-link>
            </div>
            <StockEmpty v-else-if="!state.busy" text="No Document Available" />
        </div>
        <div v-if="canWrite" class="px-4 py-2 flex items-center justify-center w-full border-t">
            <router-link class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full" :to="{ path: '/stock/ins/add', query: state.project_id ? { project_id: state.project_id } : {} }">
                <i class="ri-add-line text-xl"></i>
                <span class="text-sm mt-1"> Tambah Dokumen </span>
            </router-link>
        </div>
        <StockPager :page="state.page" :total="state.meta.total_pages" @prev="state.page--; load()" @next="state.page++; load()" />
        <StockFilterSheet :show="state.filter_open" :fields="filterFields" :values="{ direction: state.direction }" @hide="state.filter_open = false" @apply="applyFilter" />
    </main>
</template>
