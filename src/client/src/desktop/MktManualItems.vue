<script setup>
import { onMounted, reactive } from 'vue';
import { getManualItems, saveManualItem } from '../services/service';
import { loading } from '../services/router';

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
    <div class="p-8 max-w-4xl">
        <div class="text-2xl text-[#001737] font-semibold mb-1 text-left">Barang Manual</div>
        <div class="text-sm text-gray-500 mb-6 text-left">Katalog barang untuk stok manual (tanpa project). Tidak disinkronkan ke accounting/eproc.</div>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <form v-if="state.editing" class="bg-white rounded-lg shadow p-6 grid grid-cols-3 gap-4 text-left" @submit.prevent="submit">
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Kode barang</span><input v-model.trim="state.form.code" required maxlength="80" class="border rounded-lg h-45px px-3" /></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Nama barang</span><input v-model.trim="state.form.name" required maxlength="255" class="border rounded-lg h-45px px-3" /></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Satuan</span><input v-model.trim="state.form.unit" required maxlength="30" class="border rounded-lg h-45px px-3" /></label>
            <label class="flex items-center gap-2 text-sm col-span-3"><input v-model="state.form.active" type="checkbox" /> Aktif</label>
            <div class="col-span-3 flex gap-3 justify-end">
                <button type="button" class="h-45px px-6 border rounded-lg" :disabled="state.busy" @click="state.editing = false">Batal</button>
                <button class="h-45px px-6 rounded-lg bg-red-500 text-white hover:bg-red-600" :disabled="state.busy"><strong>Simpan</strong></button>
            </div>
        </form>
        <template v-else>
            <div class="flex flex-wrap justify-between gap-2 mb-6">
                <button class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:bg-red-600 flex items-center" @click="edit()"><i class="ri-add-line text-xl"></i><strong>Tambah Barang Manual</strong></button>
                <form class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300" @submit.prevent="load">
                    <input type="text" class="h-full w-full rounded-full bg-transparent pl-45px" placeholder="Search barang" v-model="state.search" />
                    <i class="ri-search-line absolute left-4 text-xl text-[#667085]"></i>
                </form>
            </div>
            <div class="w-full bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px"><tr><th class="p-3 pl-4 font-bold">Kode</th><th class="p-3 font-bold">Nama</th><th class="p-3 font-bold">Satuan</th><th class="p-3 font-bold">Status</th></tr></thead>
                    <tbody class="text-13px">
                        <tr v-for="item in state.items" :key="item.id" class="border-b h-56px cursor-pointer hover:bg-gray-50" @click="edit(item)"><td class="p-3 pl-4">{{ item.code }}</td><td class="p-3">{{ item.name }}</td><td class="p-3">{{ item.unit }}</td><td class="p-3">{{ item.active ? 'Aktif' : 'Nonaktif' }}</td></tr>
                        <tr v-if="!state.items.length"><td colspan="4" class="p-8 text-center text-sm font-medium text-gray-400">No Item Available</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
