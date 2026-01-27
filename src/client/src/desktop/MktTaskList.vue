<script setup>
import { onMounted, reactive, ref, computed } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import { useRoute, useRouter } from "vue-router";
import {
    getProjectList,
    deleteProject,
    nom,
    sortByProperty,
    currentUser,
    deletePodeposit,
    cancelPodeposit,
    getProjectTodo,
} from "../services/service";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Table from "../components/Table.vue";
import Popover from "../components/Popover.vue";
const user = currentUser.user?.user;
const confirmDelete = ref(false);
const confirmCancel = ref(false);
const router = useRouter();
const route = useRoute();
const state = reactive({
    data: [],
    meta: {},
    filter_open: false,
    search: "",
    selected: [],
    order_by: null,
    sort: null,
    // filter start
    status: [],
    invoice: [],
    action: [],
    deposit: [],
    start_date: null,
    end_date: null,
    limit: 10,
    // filter end
    po: [],
    page: 1,
    export: null,
    export_mode: "print",
    project_type: [],
    selectedc: [],
});
const deleteSelectedProject = () => {
    confirmDelete.value = false;
    loading();
    const deletePromises = state.selected?.map((project) => {
        const po = state.data.find((e) => e.id == project);
        console.log(po);
        return deletePodeposit(po.po_deposit);
    });
    Promise.all(deletePromises).finally(() => {
        search();
    });
};
const setDelete = (id) => {
    state.selected = [id];
    confirmDelete.value = true;
};
const cancelSelectedProject = () => {
    confirmCancel.value = false;
    loading();
    const cancelPromises = state.selectedc?.map((project) => {
        const po = state.data.find((e) => e.id == project);
        return cancelPodeposit(po.po_deposit);
    });
    Promise.all(cancelPromises).finally(() => {
        search();
    });
};
const setCancel = (id) => {
    state.selectedc = [id];
    confirmCancel.value = true;
};
const toggle_filter = () => {
    state.filter_open = !state.filter_open;
};
const prevPage = () => {
    if (state.meta) {
        if (state.page - 1 >= 1) {
            state.page = state.page - 1;
            search();
        }
    }
};
const nextPage = () => {
    if (state.meta) {
        if (state.page + 1 <= state.meta?.total_pages) {
            state.page = state.page + 1;
            search();
        }
    }
};
const handleSetFilter = (e, type) => {
    if (e.target.checked) {
        state[type] = [...state[type], e.target.value];
    } else {
        state[type] = state[type].filter((d) => d != e.target.value);
    }
};
const handleSetSingle = (e, type) => {
    if (e.target.checked) {
        state[type] = [e.target.value];
    } else {
        state[type] = [null];
    }
};
const applyFilter = () => {
    state.page = 1;
    state.filter_open = false;
    search();
};
const resetFilter = () => {
    state.filter_open = false;
    state.search = "";
    state.selected = [];
    state.order_by = null;
    state.sort = null;
    state.status = [];
    state.deposit = [];
    state.invoice = [];
    state.action = [];
    state.po = [];
    state.start_date = null;
    state.end_date = null;
    state.page = 1;
    state.limit = 10;
    search();
};

const goToPage = (page) => {
    state.page = page;
    search();
};
const doSearch = () => {
    state.page = 1;
    search();
};
const search = () => {
    state.selected = [];
    loading();
    // const type = state.project_type.split("-");
    let filter = {
        search: state.search,
        order_by: state.order_by,
        sort: state.sort,
        status: state.status.join(","),
        deposit: state.deposit.join(","),
        action: state.action.join(","),
        noinvoice: true,
        po: state.po.join(","),
        start_date: state.start_date,
        end_date: state.end_date,
        page: state.page,
        limit: state.limit,
        project_type: state.project_type,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "" || filter[key].length === 0) {
            delete filter[key];
        }
    });
    localStorage.setItem(state.project_type, JSON.stringify(filter));

    getProjectTodo(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data.map((e) => {
                e.invoice = e?.invoices
                    ? JSON.parse(e?.invoices).reverse()[0]
                    : null;
                e.meta = e.deadline_meta ? JSON.parse(e.deadline_meta) : {};
                return e;
            });
            state.meta = r.meta;
        } else {
            state.data = [];
            state.meta = r.meta;
        }
    });
};

const computedPages = computed(() => {
    const total = state.meta?.total_pages ?? 1;
    const current = state.page;

    if (total <= 5) {
        // When there are 5 or fewer pages, show them all.
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    if (current <= 3) {
        // When current page is near the start, show pages 1-4 and the last page.
        return [1, 2, 3, 4, total];
    }

    if (current >= total - 2) {
        // When current page is near the end, show the first page and the last 4 pages.
        return [1, total - 3, total - 2, total - 1, total];
    }

    // Otherwise, current page is in the middle: show first page, one before current, current, one after, and last page.
    return [1, current - 1, current, current + 1, total];
});

const prepareExport = (mode) => {
    state.export_mode = mode;
    let status = {
        production: "On Production",
        cancel: "Cancel",
        ready: "Ready To Deliver",
        partial: "Partial Delivery",
        delivered: "Delivered",
        new: "New",
    };
    let data = state.data.map((e) => {
        let meta = e.deadline_meta ? JSON.parse(e.deadline_meta) : {};
        let invoices = (e.invoices ? JSON.parse(e.invoices) : [])
            .map((ee) => `${ee.invoice_number} (${ee.invoice_date})`)
            .join("<br>");
        return {
            "PO Number": e.client_po_number ?? "Dalam Proses",
            "PO Date": e.client_po_date
                ? dayjs(e.client_po_date).format("DD MMM YYYY")
                : "Dalam Proses",
            "Job Number": e.job_number,
            Client: e.client_company,
            "Client's PIC": e.client_pic_name,
            "Pelangi's PIC": e.pic_name,
            Invoices: invoices,
            "Invoiced Amount": e.invoiced_amount,
            "Invoice Status": (
                e?.invoice_status ?? "Not Yet Processed"
            )?.replace(/./, (c) => c.toUpperCase()),
            "Invoice PIC": e?.invoice_pic?.replace(/./, (c) => c.toUpperCase()),
            Deposit: e.is_po_deposit ? "Yes" : "No",
            Actual: e.is_real ? "Yes" : "No",
            Price: e.total_price,
            Products: e.products_data
                .map((ee) => `${ee.name} (${nom(ee.quantity)})`)
                .join("<br>"),
            Status: status[e.status],
            "Delivery Remark":
                meta?.delivery_deadline?.notes?.replace(/\r\n/g, "<br>") ?? "",
            "Invoice Remark":
                meta?.invoice_deadline?.notes?.replace(/\r\n/g, "<br>") ?? "",
            "Po Remark":
                meta?.po_deadline?.notes?.replace(/\r\n/g, "<br>") ?? "",
            "Production Remark":
                meta?.production_deadline?.notes?.replace(/\r\n/g, "<br>") ??
                "",
        };
    });
    // console.log(data)
    state.export = data;
};
onMounted(() => {
    
    if (route.query?.filter) {
        let flt = localStorage.getItem(state.project_type);
        if (flt) {
            flt = JSON.parse(flt);
            state.search = flt?.search ?? null;
            state.order_by = flt?.order_by ?? null;
            state.sort = flt?.sort ?? null;
            state.status = flt?.status?.split(",") ?? [];
            state.po = flt?.po?.split(",") ?? [];
            state.action = flt?.po_action?.split(",") ?? [];
            state.invoice = flt?.invoice?.split(",") ?? [];
            state.deposit = flt?.deposit?.split(",") ?? [];
            state.start_date = flt?.start_date ?? null;
            state.end_date = flt?.end_date ?? null;
            state.page = flt?.page ?? null;
            state.limit = flt?.limit ?? null;
        }
    } else {
        localStorage.removeItem(state.project_type);
    }
    search();
});
</script>

<template>
    <div class="p-8">
        <div
            class="text-[#001737] font-semibold mb-6 text-left flex items-center"
        >
            <span class="text-2xl capitalize">
               Task List
            </span>
            <span class="flex-1"></span>
            <div class="flex gap-1 text-md">
                <!-- Previous Button -->
                <button
                    class="h-[40px] px-3 border bg-white rounded shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center justify-center disabled:opacity-50"
                    :disabled="state.page === 1"
                    @click="prevPage"
                >
                    <i class="ri-arrow-left-s-line"></i>
                </button>

                <!-- Pagination Buttons -->
                <template v-for="(p, index) in computedPages" :key="p">
                    <!-- Insert ellipsis if there's a gap between consecutive pages -->
                    <template
                        v-if="index > 0 && p - computedPages[index - 1] > 1"
                    >
                        <span
                            class="h-[40px] px-3 flex items-center justify-center"
                            >...</span
                        >
                    </template>
                    <button
                        class="h-[40px] px-3 flex items-center justify-center font-semibold"
                        :class="
                            state.page === p
                                ? 'border bg-white rounded shadow text-red-500 hover:shadow-sm hover:bg-gray-50 '
                                : 'text-gray-700'
                        "
                        :disabled="state.page === p"
                        @click="goToPage(p)"
                    >
                        <span>{{ p }}</span>
                    </button>
                </template>

                <!-- Next Button -->
                <button
                    class="h-[40px] px-3 border bg-white rounded shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center justify-center disabled:opacity-50"
                    :disabled="state.page === (state.meta?.total_pages ?? 1)"
                    @click="nextPage"
                >
                    <i class="ri-arrow-right-s-line"></i>
                </button>
            </div>
        </div>
        <div class="flex justify-between mb-6">
            <div class="flex gap-2">
                <div class="relative">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        @click="toggle_filter"
                    >
                        <i class="ri-filter-3-fill text-xl"></i>
                        <strong>Filters</strong>
                    </button>

                    <div
                        class="fixed top-0 left-0 w-full h-full bg-[#00000011] z-1"
                        @click="() => (state.filter_open = false)"
                        :class="state.filter_open ? 'block' : 'hidden'"
                    ></div>
                    <div
                        class="absolute z-1 mt-2 transition-all transform origin-top-left"
                        :class="
                            state.filter_open
                                ? 'max-h-300px opacity-100 scale-100'
                                : 'max-h-0 opacity-30 scale-10 overflow-hidden'
                        "
                    >
                        <div
                            class="w-300px bg-white rounded-lg shadow-2xl border"
                        >
                            <div class="w-full p-4 flex text-left">
                                <div class="w-300px p-2 pr-4">
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Project Type</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="design"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(
                                                        e,
                                                        'project_type'
                                                    )
                                            "
                                            :checked="state.project_type.includes('design')"
                                        />
                                        <span>Design</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="printing"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(
                                                        e,
                                                        'project_type'
                                                    )
                                            "
                                            :checked="state.project_type.includes('printing')"
                                        />
                                        <span>Printing</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="gimmick"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(
                                                        e,
                                                        'project_type'
                                                    )
                                            "
                                            :checked="state.project_type.includes('gimmick')"
                                        />
                                        <span>Gimmick</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="payment"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(
                                                        e,
                                                        'project_type'
                                                    )
                                            "
                                            :checked="state.project_type.includes('payment')"
                                        />
                                        <span>Supplier Payment</span>
                                    </label>
                                </div>
                            </div>
                            <div
                                class="border-t px-4 py-2 flex justify-end items-center gap-2"
                            >
                                <button
                                    class="h-32px px-4 border bg-white rounded-lg gap-2 shadow hover:shadow-sm hover:bg-gray-50 flex items-center text-sm"
                                    @click="() => (state.filter_open = false)"
                                >
                                    <strong>Cancel</strong>
                                </button>
                                <button
                                    class="h-32px px-4 border bg-white rounded-lg gap-2 shadow hover:shadow-sm hover:bg-gray-50 flex items-center text-sm"
                                    @click="resetFilter"
                                >
                                    <strong>Reset</strong>
                                </button>

                                <button
                                    class="h-32px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm"
                                    @click="applyFilter"
                                >
                                    <strong>Apply</strong>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('print')"
                    v-if="user.position !== 'delivery'"
                >
                    <i class="ri-printer-fill text-xl"></i>
                    <strong>Print</strong>
                </button> -->
                <!-- <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#1C8B54] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('excel')" v-if="user.position !== 'delivery'">
                    <i class="ri-file-excel-2-fill text-xl"></i>
                    <strong>Excel</strong>
                </button>

                <button v-if="user.position == 'marketing' || user.position == 'admin'
                    "
                    class="h-45px px-4 border border-red-600 bg-white rounded-lg gap-2 shadow text-red-600 hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="() => router.push('/desktop/projects/add?back=' + route.path)">
                    <i class="ri-add-line text-xl"></i>
                    <strong>Create</strong>
                </button> -->
                <!-- <button v-if="user.position == 'marketing' || user.position == 'admin'
                    " v-show="state.selected.length"
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-red-500 hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="confirmDelete = true">
                    <i class="ri-delete-bin-line text-xl"></i>
                    <strong>Delete Selected</strong>
                </button> -->
            </div>
            <div>
                <form
                    class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300"
                    @submit.prevent="doSearch"
                >
                    <input
                        type="text"
                        class="h-full w-full rounded-full bg-transparent pl-45px"
                        placeholder="Search"
                        v-model="state.search"
                    />
                    <i
                        class="ri-search-line absolute left-4 text-xl text-[#667085]"
                    ></i>
                </form>
            </div>
        </div>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto flex-1">
                <table class="w-full h-full border-b">
                    <thead
                        class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px"
                    >
                        <tr>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Job No</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Project Type</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Deposit</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4">PO</span>
                                </div>
                            </th>

                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Surat Jalan</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Design Approved</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Design Uploaded</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Bast Status</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >GR/TPB Status</span
                                    >
                                </div>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Status</span
                                    >
                                </div>
                            </th>

                            <th class="p-1"></th>
                        </tr>
                    </thead>
                    <tbody class="text-13px">
                        <tr
                            class="border-b"
                            v-for="(row, i) in state.data"
                            :key="i"
                        >
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        {{ row.job_number }}
                                        <div
                                            class="text-xs text-gray-400 w-200px truncate"
                                            :title="row.title"
                                        >
                                            {{ row.title }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap capitalize"
                                    >
                                    {{ row.project_type == 'payment' ? 'supplier payment' : row.project_type }}
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap capitalize"
                                    >
                                        <strong
                                            :style="`color: ${
                                                row.deposit_status ===
                                                'non-actual'
                                                    ? '#A045C9'
                                                    : row.deposit_status ===
                                                      'actual'
                                                    ? '#1973C7'
                                                    : '#19A470'
                                            }`"
                                        >
                                            {{ row.deposit_status }}
                                        </strong>
                                    </div>
                                </div>
                            </td>

                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.po_status === 'available'
                                                ? 'bg-[#6FD669]'
                                                : row.po_status ===
                                                  'not available'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.po_status }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.do_status === 'uploaded'
                                                ? 'bg-[#6FD669]'
                                                : row.do_status ===
                                                  'not uploaded'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.do_status }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.design_approved === 'approved'
                                                ? 'bg-[#6FD669]'
                                                : row.design_approved ===
                                                  'not approved'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.design_approved }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.design_files === 'uploaded'
                                                ? 'bg-[#6FD669]'
                                                : row.design_files ===
                                                  'not uploaded'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.design_files }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.bast_status === 'uploaded'
                                                ? 'bg-[#6FD669]'
                                                : row.bast_status ===
                                                  'not uploaded'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.bast_status }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex capitalize"
                                        :class="`${
                                            row.gr_status === 'uploaded'
                                                ? 'bg-[#6FD669]'
                                                : row.gr_status ===
                                                  'not uploaded'
                                                ? 'bg-red-500'
                                                : 'bg-gray-200'
                                        }`"
                                    >
                                        {{ row.gr_status }}
                                    </span>
                                </div>
                            </td>

                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                    v-if="['gimmick','printing'].includes(row.project_type)"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex"
                                        :class="
                                            row.status === 'delivered'
                                                ? 'bg-[#6FD669]'
                                                : row.status === 'partial'
                                                ? 'bg-[#4DC8E3]'
                                                : row.status === 'ready'
                                                ? 'bg-[#E5C100]'
                                                : row.status === 'cancel'
                                                ? 'bg-[#E44545]'
                                                : row?.status === 'production'
                                                ? 'bg-[#3f51b5]'
                                                : row?.status === 'new'
                                                ? 'bg-[#AD58D4]'
                                                : ''
                                        "
                                        >{{
                                            row.status === "delivered"
                                                ? "Delivered"
                                                : row.status === "partial"
                                                ? "Partial Delivery"
                                                : row.status === "ready"
                                                ? "Ready to deliver"
                                                : row.status === "cancel"
                                                ? "Cancel"
                                                : row.status === "production"
                                                ? "On Production"
                                                : row.status === "new"
                                                ? "New"
                                                : ""
                                        }}</span
                                    >

                                  
                                </div>
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                    v-else
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex"
                                        
                                        :class="
                                            row.status === 'delivered'
                                                ? 'bg-[#6FD669]'
                                                : row.status === 'partial'
                                                ? 'bg-[#4DC8E3]'
                                                : row.status === 'ready'
                                                ? 'bg-[#E44545]'
                                                : row.status === 'cancel'
                                                ? 'bg-[#E44545]'
                                                : row?.status === 'production'
                                                ? 'bg-[#E5C100]'
                                                : row?.status === 'new'
                                                ? 'bg-[#AD58D4]'
                                                : ''
                                        "
                                        >{{
                                            row.status === "delivered"
                                                ? "Submitted"
                                                : row.status === "partial"
                                                ? "Partial Delivery"
                                                : row.status === "ready"
                                                ? "Not Submitted"
                                                : row.status === "cancel"
                                                ? "Cancel"
                                                : row.status === "production"
                                                ? "On Progress"
                                                : row.status === "new"
                                                ? "New"
                                                : ""
                                        }}</span
                                    >

                                   
                                </div>
                            </td>

                            <td class="p-1 w-60px">
                                <div class="flex items-center justify-end p-1">
                                    <router-link
                                        :to="
                                            '/desktop/summary/' +
                                            row.id +
                                            '?back=' +
                                            route.path
                                        "
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Cancel Project"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </router-link>
                                    <router-link
                                        :to="
                                            '/desktop/threads/' +
                                            row.id +
                                            '?back=' +
                                            route.path
                                        "
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Threads"
                                        >
                                            <i class="ri-chat-history-line"></i>
                                        </div>
                                    </router-link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- <div class="flex justify-between px-6 py-2 items-center">
                <button
                    class="text-sm border rounded-md px-4 py-2 bg-white"
                    @click="prevPage"
                    :class="
                        state.page <= 1
                            ? 'filter brightness-90 cursor-default text-gray-400'
                            : 'hover:bg-gray-50'
                    "
                >
                    Previous
                </button>
                <div class="text-sm">
                    Page {{ state.page }} of {{ state.meta?.total_pages || 1 }}
                </div>
                <button
                    class="text-sm border rounded-md px-4 py-2 bg-white"
                    @click="nextPage"
                    :class="
                        state.page >= state.meta?.total_pages
                            ? 'filter brightness-90 cursor-default text-gray-400'
                            : 'hover:bg-gray-50'
                    "
                >
                    Next
                </button>
            </div> -->
        </div>
    </div>
    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected project?"
        buttonText="Yes, Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedProject"
    >
    </Confirm>
    <Confirm
        type="fail"
        title="Cancel Confirmation"
        content="Are you sure want to cancel selected project?"
        buttonText="Yes, Cancel"
        :show="confirmCancel != false"
        @hide="confirmCancel = false"
        @fire="cancelSelectedProject"
    >
    </Confirm>

    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
