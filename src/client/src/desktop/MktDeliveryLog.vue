<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import { useRoute, useRouter } from "vue-router";
import {
    getInventoriess,
    getLogsList,
    getProject,
    getDelivery,
    deleteDelivery,
    updateDelivery,
    nom,
    goto,
    APIURL,
    ASSETSURL,
} from "../services/service";
import { loading } from "../services/router";
import Table1 from "../components/Table1.vue";

const router = useRouter();
const route = useRoute();
const currentUrl = route.path;
const state = reactive({
    data: [],
    deliveries: [],
    project: null,
    inventory: [],
    id: null,
    export: null,
    export_proj: null,
    export_prod: null,
    export_mode: "print",
    sort_product: false,
    viewFile: null,
    manageModal: null,
    selectedDelivery: null,
    confirmDelete: null,
    confirmDeleteItem: null,
    editingItem: null,
    editQuantity: 0,
});
const prepareExport = (mode) => {
    state.export_mode = mode;
    let status = {
        ready: "Ready To Deliver",
        partial: "Partial Delivery",
        delivered: "Delivered",
    };
    let data = state.data.map((e) => ({
        "Surat Jalan": e.delivery_data?.do_number,
        Product: e.product_data?.name,
        Quantity: e.quantity,
        Origin: e.origin_data?.name,
        Destination: e.destination_data?.name,
        "Delivery Date": dayjs(e.delivered_at).format("DD MMM YYYY"),
    }));
    state.export_prod = state.project?.products_data.map((e) => ({
        "Product Name": e.name,
        Quantity: e.quantity,
        Description: e.description?.replace(/(?:\r\n|\r|\n)/g, "<br>"),
        Stored: state.inventory
            .filter(
                (f) => f.quantity > 0 && f.product === e.id && f.warehouse !== 2
            )
            .map((f) => `${f.warehouse_data?.name} (${f.quantity})`)
            .join("<br>"),
        Delivered:
            state.inventory
                .filter(
                    (f) =>
                        f.quantity > 0 &&
                        f.product === e.id &&
                        f.warehouse === 2
                )
                .map((f) => `${f.quantity}`)
                .join("") ?? 0,
    }));
    state.export_proj = [
        {
            "Job Number": state.project?.job_number,
            "PO Number": state.project?.client_po_number ?? "Dalam Proses",
            "Client Company": state.project?.client_company,
            "Client PIC": state.project?.client_pic_name,
            "PO Date": state.project?.client_po_date
                ? dayjs(state.project?.client_po_date).format("DD MMM YYYY")
                : "Dalam Proses",
            Stored: state.project?.stored,
            Delivered: state.project?.delivered,
            Status: status[state.project?.status],
        },
    ];
    state.export = data ?? [];
};

const openManageModal = (delivery) => {
    state.selectedDelivery = delivery;
    state.manageModal = true;
    // Load delivery details with items
    loading();
    getDelivery(delivery.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.selectedDelivery = r.data;
        }
    });
};

const closeManageModal = () => {
    state.manageModal = null;
    state.selectedDelivery = null;
    state.editingItem = null;
    state.editQuantity = 0;
};

const confirmDeleteDelivery = (delivery) => {
    state.confirmDelete = delivery;
};

const cancelDeleteDelivery = () => {
    state.confirmDelete = null;
};

const executeDeleteDelivery = async () => {
    if (!state.confirmDelete) return;
    loading();
    const result = await deleteDelivery(state.confirmDelete.id);
    loading(false);
    if (result.code === 200) {
        state.confirmDelete = null;
        // Refresh data
        refreshData();
    } else {
        alert("Failed to delete delivery. Please try again.");
    }
};

const startEditItem = (item) => {
    state.editingItem = item;
    state.editQuantity = item.quantity;
};

const cancelEditItem = () => {
    state.editingItem = null;
    state.editQuantity = 0;
};

const saveEditItem = async () => {
    if (!state.editingItem || !state.selectedDelivery) return;
    
    loading();
    const updatedItems = state.selectedDelivery.delivery_items_data.map(item => {
        if (item.id === state.editingItem.id) {
            return {
                ...item,
                quantity: parseInt(state.editQuantity),
                actual_quantity: parseInt(state.editQuantity),
            };
        }
        return item;
    });

    const payload = {
        project: state.selectedDelivery.project,
        default_origin: state.selectedDelivery.default_origin,
        destination: state.selectedDelivery.destination,
        shipping_vendor: state.selectedDelivery.shipping_vendor,
        delivery_date: state.selectedDelivery.delivery_date,
        do_number: state.selectedDelivery.do_number,
        do_files: state.selectedDelivery.do_files,
        receipt_files: state.selectedDelivery.receipt_files,
        delivery_items: updatedItems,
    };

    const result = await updateDelivery(state.selectedDelivery.id, payload);
    loading(false);
    
    if (result.code === 200) {
        state.editingItem = null;
        state.editQuantity = 0;
        // Refresh delivery details
        getDelivery(state.selectedDelivery.id).then((r) => {
            if (r.code === 200) {
                state.selectedDelivery = r.data;
            }
        });
        // Refresh main data
        refreshData();
    } else {
        alert("Failed to update item. Please try again.");
    }
};

const confirmDeleteItem = (item) => {
    state.confirmDeleteItem = item;
};

const cancelDeleteItem = () => {
    state.confirmDeleteItem = null;
};

const executeDeleteItem = async () => {
    if (!state.confirmDeleteItem || !state.selectedDelivery) return;
    
    loading();
    const updatedItems = state.selectedDelivery.delivery_items_data.filter(
        item => item.id !== state.confirmDeleteItem.id
    );

    const payload = {
        project: state.selectedDelivery.project,
        default_origin: state.selectedDelivery.default_origin,
        destination: state.selectedDelivery.destination,
        shipping_vendor: state.selectedDelivery.shipping_vendor,
        delivery_date: state.selectedDelivery.delivery_date,
        do_number: state.selectedDelivery.do_number,
        do_files: state.selectedDelivery.do_files,
        receipt_files: state.selectedDelivery.receipt_files,
        delivery_items: updatedItems,
    };

    const result = await updateDelivery(state.selectedDelivery.id, payload);
    loading(false);
    
    if (result.code === 200) {
        state.confirmDeleteItem = null;
        // Refresh delivery details
        getDelivery(state.selectedDelivery.id).then((r) => {
            if (r.code === 200) {
                state.selectedDelivery = r.data;
            }
        });
        // Refresh main data
        refreshData();
    } else {
        alert("Failed to delete item. Please try again.");
    }
};

const refreshData = () => {
    getLogsList(state.id).then((r) => {
        if (r.code === 200) {
            state.data = r.data;
            state.deliveries = r.dels.map((e) => {
                e.do_files = JSON.parse(e.do_files ?? '[]');
                e.receipt_files = JSON.parse(e.receipt_files ?? '[]');
                if (e.do_file) {
                    e.do_files.push(e.do_file);
                }
                return e;
            });
        }
    });
};

watch(
    () => state.sort_product,
    (sort) => {
        if (sort) {
            state.data.sort((a, b) => {
                return a.product - b.product;
            });
        } else {
            state.data.sort((a, b) => {
                return a.id - b.id;
            });
        }
    },
    { deep: true }
);
onMounted(() => {
    loading();
    state.id = currentUrl.split("/").slice(-1);

    getInventoriess(state.id).then((r) => {
        if (r.code === 200) {
            state.inventory = r.data;
            getProject(state.id).then((rr) => {
                if (rr.code === 200) {
                    state.project = rr.data;
                    state.project.products_data.map((p) => {
                        p.description = p.description?.replace(
                            /(?:\r\n|\r|\n)/g,
                            "<br>"
                        );
                        p.stored = state.inventory
                            .filter(
                                (e) =>
                                    e.quantity > 0 &&
                                    e.product === p.id &&
                                    e.warehouse !== 2
                            )
                            .map(
                                (e) =>
                                    `<span>- ${e.warehouse_data?.name} (${nom(
                                        e.quantity
                                    )})</span>`
                            )
                            .join("");
                        p.delivered = Number(
                            state.inventory
                                .filter(
                                    (e) =>
                                        e.quantity > 0 &&
                                        e.product === p.id &&
                                        e.warehouse === 2
                                )
                                .map((e) => e.quantity)
                                .join("")
                        );
                        p.status =
                            p.delivered == 0
                                ? "ready"
                                : p.delivered >= p.quantity
                                    ? "delivered"
                                    : "partial";
                        return p;
                    });
                }
                loading(false);
            });
        }
    });
    getLogsList(state.id).then((r) => {
        if (r.code === 200) {
            state.data = r.data;
            state.deliveries = r.dels.map((e) => {
                e.do_files = JSON.parse(e.do_files ?? '[]');
                e.receipt_files = JSON.parse(e.receipt_files ?? '[]');
                if (e.do_file) {
                    e.do_files.push(e.do_file);
                }
                return e;
            });

        }
    });
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center">
            <div class="cursor-pointer mr-4" @click="goto('/desktop/delivery-report?filter=true')">
                <i class="ri-arrow-left-line"></i>
            </div>
            Delivery History
        </div>
        <div class="flex justify-between mb-6">
            <div class="flex gap-2">
                <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('print')">
                    <i class="ri-printer-fill text-xl"></i>
                    <strong>Print</strong>
                </button>
                <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#1C8B54] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('excel')">
                    <i class="ri-file-excel-2-fill text-xl"></i>
                    <strong>Excel</strong>
                </button>
            </div>
        </div>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-bold">Project Data</div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full h-full border-b">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                        <tr>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Job Number</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">PO Number</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Client</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">PO Date</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Stored</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Delivered</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Status</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-13px">
                        <tr class="border-b">
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ state.project?.job_number }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{
                                        state.project?.client_po_number ??
                                        "Dalam Proses"
                                    }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-start justify-center p-1 whitespace-nowrap flex-col">
                                    {{ state.project?.client_company }}
                                    <span class="text-xs text-gray-400">
                                        {{ state.project?.client_pic_name }}
                                    </span>
                                </div>
                            </td>

                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{
                                        state.project?.client_po_date
                                            ? dayjs(
                                                state.project?.client_po_date
                                            ).format("DD MMM YYYY")
                                            : "Dalam Proses"
                                    }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <strong>{{
                                        nom(state.project?.stored ?? 0)
                                        }}</strong>/{{ nom(state.project?.total) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <strong>{{
                                        nom(state.project?.delivered ?? 0)
                                        }}</strong>/{{ nom(state.project?.total) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <span class="rounded-full px-2 py-1 text-11px font-medium text-white" :class="state.project?.status ===
                                            'delivered'
                                            ? 'bg-[#6FD669]'
                                            : state.project?.status ===
                                                'partial'
                                                ? 'bg-[#4DC8E3]'
                                                : state.project?.status ===
                                                    'ready'
                                                    ? 'bg-[#E5C100]'
                                                    : state.project?.status ===
                                                        'cancel'
                                                        ? 'bg-[#E44545]'
                                                        : ''
                                        ">{{
                                            state.project?.status ===
                                                "delivered"
                                                ? "Delivered"
                                                : state.project?.status ===
                                                    "partial"
                                                    ? "Partial Delivery"
                                                    : state.project?.status ===
                                                        "ready"
                                                        ? "Ready to deliver"
                                                        : state.project?.status ===
                                                            "cancel"
                                                            ? "Cancel"
                                                            : ""
                                        }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-bold">Product Data</div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full h-full border-b">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                        <tr>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Product</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Quantity</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Description</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Stored</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Delivered</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Status</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-13px">
                        <tr class="border-b" v-for="(row, i) in state.project?.products_data" :key="i">
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ nom(row.quantity) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <span v-html="row.description"></span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <span class="flex flex-col gap-1" v-html="row.stored"></span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ nom(row.delivered) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    <span class="rounded-full px-2 py-1 text-11px font-medium text-white" :class="row.status === 'delivered'
                                            ? 'bg-[#6FD669]'
                                            : row.status === 'partial'
                                                ? 'bg-[#4DC8E3]'
                                                : row.status === 'ready'
                                                    ? 'bg-[#E5C100]'
                                                    : row.status === 'cancel'
                                                        ? 'bg-[#E44545]'
                                                        : ''
                                        ">{{
                                            row.status === "delivered"
                                                ? "Delivered"
                                                : row.status === "partial"
                                                    ? "Partial Delivery"
                                                    : row.status === "ready"
                                                        ? "Ready to deliver"
                                                        : row.status === "cancel"
                                                            ? "Cancel"
                                                            : ""
                                        }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="flex border-b px-4 py-2 items-center">
                <div class="text-sm font-bold">Surat Jalan</div>
            </div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full h-full border-b">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                        <tr>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Nomor Surat Jalan</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Shipping Vendor</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Origin</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Destination</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Delivery Date</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">File Surat Jalan</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">File Barang Diterima</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-center py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Actions</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-13px">
                        <tr class="border-b" v-for="(row, i) in state.deliveries.filter((row) => row?.status === 'delivered')" :key="i">
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row?.do_number }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row?.shipping_vendor_data?.name }}
                                </div>
                            </td>

                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.default_origin_data?.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.destination_data?.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{
                                        dayjs(
                                            row?.delivery_date
                                        ).format("DD MMM YYYY")
                                    }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div >

                                    <div class="flex gap-2 overflow-x-auto px-4 py-2 border-b"
                                        v-if="row?.do_files?.length">
                                        <div class="flex items-center w-60px h-60px  flex-shrink-0 rounded border relative"
                                            v-for="(file, i) in row?.do_files" @click="state.viewFile = file">
                                            <img :src="ASSETSURL + file" alt="" class="w-full h-full object-cover">
                                        </div>
                                    </div>

                                </div>
                            </td>
                            <td class="p-1">
                                <div >
                                    <div class="flex gap-2 overflow-x-auto px-4 py-2 border-b"
                                        v-if="row?.receipt_files?.length">
                                        <div class="flex items-center w-60px h-60px  flex-shrink-0 rounded border relative"
                                            v-for="(file, i) in row?.receipt_files" @click="state.viewFile = file">
                                            <img :src="ASSETSURL + file" alt="" class="w-full h-full object-cover">
                                        </div>
                                    </div>

                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-center gap-2 py-1 px-4">
                                    <button 
                                        class="text-blue-500 hover:bg-blue-50 px-2 py-1 rounded" 
                                        title="Manage Items"
                                        @click="openManageModal(row)">
                                        <i class="ri-settings-4-line text-lg"></i>
                                    </button>
                                    <button 
                                        class="text-red-500 hover:bg-red-50 px-2 py-1 rounded" 
                                        title="Delete Delivery"
                                        @click="confirmDeleteDelivery(row)">
                                        <i class="ri-delete-bin-line text-lg"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <br>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="flex border-b px-4 py-2 items-center">
                <div class="text-sm font-bold">Delivery Data</div>
                <div class="text-sm font-medium ml-auto border py-1 px-2 cursor-pointer rounded bg-red-500 text-white"
                    :class="state.sort_product ? 'opacity-100' : 'opacity-50'"
                    @click="state.sort_product = !state.sort_product">
                    Sort by product
                </div>
            </div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full h-full border-b">
                    <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                        <tr>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Surat Jalan</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Product</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Quantity</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Origin</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Destination</span>
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group">
                                    <span class="font-bold leading-4">Delivery Date</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-13px">
                        <tr class="border-b" v-for="(row, i) in state.data" :key="i">
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.delivery_data?.do_number }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.product_data?.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ nom(row.actual_quantity) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.origin_data?.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{ row.destination_data?.name }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex items-center justify-start p-1 whitespace-nowrap">
                                    {{
                                        dayjs(
                                            row.delivery_data?.delivery_date
                                        ).format("DD MMM YYYY")
                                    }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <Table1 :data="state.export?.length ? state.export : null" :proj="state.export_proj" :prod="state.export_prod"
        :action="state.export_mode" @close="
            (state.export = null),
            (state.export_proj = null),
            (state.export_prod = null)
            " />

    <div class="fixed bottom-0 left-0 right-0 top-0 z-60 bg-black/70 flex items-center justify-center" v-if="state.viewFile">
        <img :src="ASSETSURL + state.viewFile" alt="" class="max-w-full max-h-full object-contain">
        <i class="ri-close-circle-fill text-red-500 text-4xl absolute top-2 w-32px h-32px block flex items-center justify-center right-2 bg-white rounded-full"
            @click="state.viewFile = null"></i>

    </div>

    <!-- Manage Delivery Modal -->
    <div class="fixed bottom-0 left-0 right-0 top-0 z-50 bg-black/50 flex items-center justify-center p-4" v-if="state.manageModal" @click.self="closeManageModal">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-90vh overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Manage Delivery Items</h3>
                    <p class="text-sm text-gray-500">Surat Jalan: {{ state.selectedDelivery?.do_number }}</p>
                </div>
                <button @click="closeManageModal" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1">
                <!-- Delivery Info -->
                <div class="bg-blue-50 p-4 rounded-lg mb-4 text-sm">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-gray-500">Origin:</span>
                            <span class="ml-2 font-medium">{{ state.selectedDelivery?.default_origin_data?.name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Destination:</span>
                            <span class="ml-2 font-medium">{{ state.selectedDelivery?.destination_data?.name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Delivery Date:</span>
                            <span class="ml-2 font-medium">{{ state.selectedDelivery?.delivery_date ? dayjs(state.selectedDelivery.delivery_date).format('DD MMM YYYY') : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium text-white"
                                :class="state.selectedDelivery?.status === 'delivered' ? 'bg-green-500' : 
                                        state.selectedDelivery?.status === 'partial' ? 'bg-blue-400' : 'bg-yellow-500'">
                                {{ state.selectedDelivery?.status }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="w-full border text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 border-b font-medium text-gray-600">Product</th>
                            <th class="text-left p-3 border-b font-medium text-gray-600">Quantity</th>
                            <th class="text-left p-3 border-b font-medium text-gray-600">Origin</th>
                            <th class="text-left p-3 border-b font-medium text-gray-600">Destination</th>
                            <th class="text-center p-3 border-b font-medium text-gray-600 w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in state.selectedDelivery?.delivery_items_data" :key="item.id" class="border-b hover:bg-gray-50">
                            <td class="p-3">{{ item.product_data?.name }}</td>
                            <td class="p-3">
                                <div v-if="state.editingItem?.id === item.id" class="flex items-center gap-2">
                                    <input 
                                        type="number" 
                                        v-model="state.editQuantity"
                                        class="border rounded px-2 py-1 w-20 text-center"
                                        min="1"
                                    />
                                </div>
                                <span v-else>{{ nom(item.quantity) }}</span>
                            </td>
                            <td class="p-3">{{ item.origin_data?.name }}</td>
                            <td class="p-3">{{ item.destination_data?.name }}</td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-1">
                                    <template v-if="state.editingItem?.id === item.id">
                                        <button 
                                            class="text-green-600 hover:bg-green-50 p-1.5 rounded"
                                            @click="saveEditItem"
                                            title="Save">
                                            <i class="ri-check-line"></i>
                                        </button>
                                        <button 
                                            class="text-gray-500 hover:bg-gray-100 p-1.5 rounded"
                                            @click="cancelEditItem"
                                            title="Cancel">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button 
                                            class="text-blue-500 hover:bg-blue-50 p-1.5 rounded"
                                            @click="startEditItem(item)"
                                            title="Edit Quantity">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button 
                                            class="text-red-500 hover:bg-red-50 p-1.5 rounded"
                                            @click="confirmDeleteItem(item)"
                                            title="Delete Item">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!state.selectedDelivery?.delivery_items_data?.length">
                            <td colspan="5" class="p-8 text-center text-gray-500">
                                No items found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2">
                <button 
                    class="px-4 py-2 border rounded-lg hover:bg-gray-100 text-gray-700"
                    @click="closeManageModal">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Delivery Modal -->
    <div class="fixed bottom-0 left-0 right-0 top-0 z-50 bg-black/50 flex items-center justify-center p-4" v-if="state.confirmDelete" @click.self="cancelDeleteDelivery">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-error-warning-line text-3xl text-red-500"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Delivery?</h3>
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete Surat Jalan <strong>{{ state.confirmDelete.do_number }}</strong>?<br>
                    This action cannot be undone.
                </p>
                <div class="flex justify-center gap-3">
                    <button 
                        class="px-4 py-2 border rounded-lg hover:bg-gray-100 text-gray-700"
                        @click="cancelDeleteDelivery">
                        Cancel
                    </button>
                    <button 
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                        @click="executeDeleteDelivery">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Item Modal -->
    <div class="fixed bottom-0 left-0 right-0 top-0 z-50 bg-black/50 flex items-center justify-center p-4" v-if="state.confirmDeleteItem" @click.self="cancelDeleteItem">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-error-warning-line text-3xl text-red-500"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Item?</h3>
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete item <strong>{{ state.confirmDeleteItem.product_data?.name }}</strong>?<br>
                    This action cannot be undone.
                </p>
                <div class="flex justify-center gap-3">
                    <button 
                        class="px-4 py-2 border rounded-lg hover:bg-gray-100 text-gray-700"
                        @click="cancelDeleteItem">
                        Cancel
                    </button>
                    <button 
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                        @click="executeDeleteItem">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
