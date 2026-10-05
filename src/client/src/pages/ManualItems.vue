<script setup>
import { onMounted, reactive } from 'vue';
import Navbar from '../components/Navbar.vue';
import { getManualItems, saveManualItem } from '../services/service';
import { loading } from '../services/router';
import StockSearchBar from '../components/StockSearchBar.vue';
import StockEmpty from '../components/StockEmpty.vue';

const empty = () => ({ id: null, code: '', name: '', unit: 'pcs', active: true });
const state = reactive({ items: [], search: '', form: empty(), editing: false, busy: false, error: '' });
const load = async () => {
    state.error = '';
    loading();
    try {
        state.items = (await getManualItems({ search: state.search })).data;
    } catch (error) {
        state.error = error.message;
    } finally {
        loading(false);
    }
};
const edit = (item = null) => {
    state.form = item ? { ...item } : empty();
    state.editing = true;
    state.error = '';
};
const submit = async () => {
    if (state.busy) return;
    state.busy = true;
    state.error = '';
    try {
        const { id, code, name, unit, active } = state.form;
        await saveManualItem(id, { code, name, unit, active });
        state.editing = false;
        await load();
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
    }
};
onMounted(load);
</script>

<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Barang Manual" :back="'/stock'" />
        <p v-if="state.error" role="alert" class="px-4 py-2 text-red-600 text-xs">{{ state.error }}</p>
        <form v-if="state.editing" class="flex-1 overflow-auto flex flex-col py-2" @submit.prevent="submit">
            <label class="flex flex-col items-start gap-1 px-4 py-2 w-full"><span class="text-xs text-blue-gray-400">Kode barang</span><input v-model.trim="state.form.code" required maxlength="80" class="border-b w-full h-10 bg-transparent text-sm text-black" placeholder="BRG-001" /></label>
            <label class="flex flex-col items-start gap-1 px-4 py-2 w-full"><span class="text-xs text-blue-gray-400">Nama barang</span><input v-model.trim="state.form.name" required maxlength="255" class="border-b w-full h-10 bg-transparent text-sm text-black" /></label>
            <label class="flex flex-col items-start gap-1 px-4 py-2 w-full"><span class="text-xs text-blue-gray-400">Satuan</span><input v-model.trim="state.form.unit" required maxlength="30" class="border-b w-full h-10 bg-transparent text-sm text-black" /></label>
            <label class="flex items-center gap-3 px-4 py-2 text-sm"><input v-model="state.form.active" type="checkbox" /> Aktif</label>
            <div class="px-4 py-2 flex gap-2 mt-auto">
                <button type="button" class="flex px-3 h-12 items-center justify-center border rounded-full flex-1" :disabled="state.busy" @click="state.editing = false"><span class="text-sm mt-1">Batal</span></button>
                <button class="flex px-3 h-12 items-center justify-center bg-app-500 text-white rounded-full flex-1" :disabled="state.busy"><span class="text-sm mt-1">Simpan</span></button>
            </div>
        </form>
        <template v-else>
            <StockSearchBar v-model="state.search" placeholder="Cari barang..." no-filter @search="load" />
            <p class="px-4 py-2 text-xs text-gray-500 bg-gray-50">Katalog barang untuk stok manual (tanpa project). Tidak disinkronkan ke accounting/eproc.</p>
            <div class="flex-1 overflow-auto">
                <div v-if="state.items.length" class="min-h-full min-w-full flex flex-col bg-gray-50 gap-1">
                    <div v-for="item in state.items" :key="item.id" class="rounded-none bg-white border-b px-4 py-2 flex justify-between items-center" @click="edit(item)">
                        <div class="flex flex-col items-start">
                            <span class="font-semibold text-black text-sm">{{ item.name }}</span>
                            <span class="text-app-900 text-xs">{{ item.code }}</span>
                        </div>
                        <div class="flex flex-col items-end text-xs">
                            <span class="text-black">{{ item.unit }}</span>
                            <span v-if="!item.active" class="text-gray-400">Nonaktif</span>
                        </div>
                    </div>
                </div>
                <StockEmpty v-else text="No Item Available" />
            </div>
            <div class="px-4 py-2 flex items-center justify-center w-full border-t">
                <button class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full" @click="edit()">
                    <i class="ri-add-line text-xl"></i>
                    <span class="text-sm mt-1"> Tambah Barang Manual </span>
                </button>
            </div>
        </template>
    </main>
</template>
