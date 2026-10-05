<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import dayjs from 'dayjs';
import { ASSETSURL, getStockIn, getStockInOptions, nom, saveStockIn, upload } from '../services/service';
import { loading } from '../services/router';
import { compressImage } from '../utils/imageCompression';

const route = useRoute();
const router = useRouter();
const editId = route.params.id || null;
const state = reactive({
    direction: route.query.direction === 'out' ? 'out' : 'in',
    scope: route.query.project_id ? 'project' : 'manual', project_id: route.query.project_id || '',
    warehouse: '', origin: '', document_date: dayjs().format('YYYY-MM-DD'),
    do_number: '', notes: '', files: [], items: [{ id: null, selected: '', quantity: 1 }],
    options: { projects: [], products: [], manual_items: [], warehouses: [], origins: [], origin_stock: [] }, busy: false, uploading: false, error: '',
});
const products = computed(() => (state.scope === 'project' ? state.options.products : state.options.manual_items));
const originStock = (product) => state.options.origin_stock.find((row) => row.type === (state.scope === 'project' ? 'product' : 'manual') && row.item_id === product.id)?.quantity || 0;
const optionLabel = (product) => {
    const base = state.scope === 'project' ? product.name : `${product.name} / ${product.code} (${product.unit})`;
    if (state.direction === 'in' && state.origin) return `${base} (stok asal ${nom(originStock(product), 0)})`;
    return state.scope === 'project' ? `${base} (qty order ${nom(product.quantity, 0)})` : base;
};
const loadOptions = async () => {
    const selected = state.scope === 'project' ? state.project_id : '';
    try {
        const result = await getStockInOptions({ ...(selected ? { project_id: selected } : {}), ...(state.direction === 'in' && state.origin ? { origin_id: state.origin } : {}) });
        if (selected !== (state.scope === 'project' ? state.project_id : '')) return;
        state.options = result.data;
    } catch (error) {
        state.error = error.message;
    }
};
const changeContext = () => {
    state.items = [{ id: null, selected: '', quantity: 1 }];
    state.options.products = [];
    if (state.scope === 'project') state.direction = 'in';
    if (state.scope === 'manual') state.project_id = '';
    loadOptions();
};
const loadDocument = async () => {
    const doc = (await getStockIn(editId)).data;
    state.direction = doc.direction;
    state.scope = doc.project ? 'project' : 'manual';
    state.project_id = doc.project || '';
    state.warehouse = doc.warehouse;
    state.origin = doc.origin || '';
    state.document_date = String(doc.document_date).slice(0, 10);
    state.do_number = doc.do_number;
    state.notes = doc.notes || '';
    state.files = JSON.parse(doc.files || '[]');
    state.items = doc.items.map((item) => ({ id: item.id, selected: item.product ?? item.manual_item, quantity: item.quantity }));
};
const uploadFile = async (event) => {
    const file = event.target.files?.[0];
    if (!file || state.uploading) return;
    state.error = '';
    state.uploading = true;
    try {
        if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) throw new Error('Gunakan JPG, PNG, atau PDF.');
        if (state.files.length >= 20) throw new Error('Maksimal 20 lampiran.');
        const processed = file.type.startsWith('image/') ? await compressImage(file, 2000, 0.8) : file;
        if (processed.size > 4 * 1024 * 1024) throw new Error('Ukuran lampiran maksimal 4 MB.');
        const result = await upload(processed);
        if (!result.path) throw new Error(result.message || 'Upload gagal.');
        state.files.push(result.path);
    } catch (error) {
        state.error = error.message;
    } finally {
        state.uploading = false;
        event.target.value = '';
    }
};
const submit = async () => {
    if (state.busy || state.uploading) return;
    state.busy = true;
    state.error = '';
    loading();
    try {
        if (!state.items.length || state.items.some((item) => !item.selected || !Number.isInteger(Number(item.quantity)) || Number(item.quantity) <= 0)) throw new Error('Pilih barang dan isi qty bulat positif.');
        if (new Set(state.items.map((item) => Number(item.selected))).size !== state.items.length) throw new Error('Barang tidak boleh duplikat.');
        const result = await saveStockIn(editId, {
            direction: state.direction, project: state.scope === 'project' ? Number(state.project_id) : null,
            warehouse: Number(state.warehouse), origin: state.direction === 'in' && state.origin ? Number(state.origin) : null,
            document_date: state.document_date, do_number: state.do_number, notes: state.notes || null, files: state.files,
            items: state.items.map((item) => ({
                [state.scope === 'project' ? 'product' : 'manual_item']: Number(item.selected), quantity: Number(item.quantity),
            })),
        });
        await router.replace('/desktop/stock/ins/' + result.data.id);
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        loading(false);
    }
};
onMounted(async () => {
    try {
        if (editId) await loadDocument();
    } catch (error) {
        state.error = error.message;
    }
    await loadOptions();
});
</script>

<template>
    <div class="p-8 max-w-4xl">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">{{ editId ? 'Ubah Dokumen Stok' : state.direction === 'out' ? 'Stock Out Baru' : 'Stock In Baru' }}</div>
        <p class="mb-4 text-sm text-gray-500 text-left">Catat barang yang sudah datang. Stok langsung bertambah/berkurang saat disimpan, sebesar qty yang Anda isi. Barang datang bertahap? Buat dokumen terpisah untuk tiap kedatangan. Jika memilih Asal, dokumen menjadi transfer: stok asal berkurang, qty maksimal = stok di asal.</p>
        <p v-if="state.error" role="alert" class="mb-4 text-sm text-red-600 text-left">{{ state.error }}</p>
        <form class="bg-white rounded-lg shadow p-6 grid grid-cols-2 gap-4 text-left" @submit.prevent="submit">
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Jenis</span><select v-model="state.direction" :disabled="state.scope === 'project'" class="border rounded-lg h-45px px-3 bg-white"><option value="in">Stock In</option><option value="out">Stock Out</option></select></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Barang</span><select v-model="state.scope" :disabled="!!editId" class="border rounded-lg h-45px px-3 bg-white" @change="changeContext"><option value="manual">Manual / tanpa project</option><option value="project">Produk project</option></select></label>
            <label v-if="state.scope === 'project'" class="flex flex-col gap-1 col-span-2"><span class="text-xs text-gray-500">Project</span><select v-model="state.project_id" required :disabled="!!editId" class="border rounded-lg h-45px px-3 bg-white" @change="changeContext"><option value="" disabled>Pilih project</option><option v-for="item in state.options.projects" :key="item.id" :value="item.id">{{ item.job_number }} / {{ item.client_po_number || 'Tanpa PO' }}</option></select></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Nomor DO / surat jalan *</span><input v-model.trim="state.do_number" required maxlength="255" class="border rounded-lg h-45px px-3" /></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Tanggal dokumen</span><input v-model="state.document_date" type="date" required class="border rounded-lg h-45px px-3" /></label>
            <label v-if="state.direction === 'in'" class="flex flex-col gap-1"><span class="text-xs text-gray-500">Asal (opsional, transfer dari gudang)</span><select v-model="state.origin" @change="loadOptions" class="border rounded-lg h-45px px-3 bg-white"><option value="">Tidak dipilih</option><option v-for="item in state.options.origins" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">{{ state.direction === 'in' ? 'Gudang penerima' : 'Gudang asal' }}</span><select v-model="state.warehouse" required  class="border rounded-lg h-45px px-3 bg-white"><option value="" disabled>Pilih gudang</option><option v-for="item in state.options.warehouses" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
            <div class="col-span-2 border-t pt-4">
                <div class="flex justify-between items-center mb-2"><span class="text-xs font-semibold text-gray-500 uppercase">Barang dan qty</span><router-link v-if="state.scope === 'manual'" to="/desktop/stock/items" class="text-xs text-[#306DCE]" target="_blank" rel="noopener">Kelola barang</router-link></div>
                <div v-for="(item, index) in state.items" :key="index" class="flex gap-3 items-end mb-2">
                    <label class="flex flex-col gap-1 flex-1"><span class="text-xs text-gray-500">Barang</span><select v-model="item.selected" required class="border rounded-lg h-45px px-3 bg-white"><option value="" disabled>Pilih barang</option><option v-for="product in products" :key="product.id" :value="product.id">{{ optionLabel(product) }}</option></select></label>
                    <label class="flex flex-col gap-1 w-40"><span class="text-xs text-gray-500">Qty</span><input v-model.number="item.quantity" type="number" min="1" max="1000000000" step="1" required class="border rounded-lg h-45px px-3" /></label>
                    <button type="button" class="h-45px px-3 text-red-600" @click="state.items.splice(index, 1)">Hapus</button>
                </div>
                <button type="button" class="h-45px px-4 border rounded-lg text-[#306DCE] flex items-center gap-2" :disabled="state.items.length >= 200" @click="state.items.push({ id: null, selected: '', quantity: 1 })"><i class="ri-add-line text-xl"></i><strong>Tambah Barang</strong></button>
            </div>
            <label class="flex flex-col gap-1 col-span-2"><span class="text-xs text-gray-500">Catatan</span><textarea v-model="state.notes" maxlength="5000" rows="2" class="border rounded-lg px-3 py-2" /></label>
            <div class="col-span-2">
                <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Lampiran DO / bukti (opsional)</span><input type="file" accept="image/jpeg,image/png,application/pdf" :disabled="state.uploading || state.busy" class="text-sm" @change="uploadFile" /></label>
                <div v-for="(file, index) in state.files" :key="file + index" class="flex gap-4 py-1 text-sm"><a :href="ASSETSURL + file" target="_blank" rel="noopener noreferrer" class="text-[#306DCE]">Lampiran {{ index + 1 }}</a><button type="button" class="text-red-600" @click="state.files.splice(index, 1)">Hapus</button></div>
            </div>
            <div class="col-span-2 flex gap-3 justify-end border-t pt-4">
                <button type="button" class="h-45px px-6 border rounded-lg" @click="router.back()">Batal</button>
                <button class="h-45px px-6 rounded-lg bg-red-500 text-white hover:bg-red-600" :disabled="state.busy || state.uploading"><strong>Simpan</strong></button>
            </div>
        </form>
    </div>
</template>
