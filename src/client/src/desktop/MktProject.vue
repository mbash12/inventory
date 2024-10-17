<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import { useRoute, useRouter } from "vue-router";
import {
    getProjectList,
    deleteProject,
    nom,
    sortByProperty,
    currentUser,
} from "../services/service";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Table from "../components/Table.vue";
import Popover from "../components/Popover.vue";
const user = currentUser.user?.user;
const confirmDelete = ref(false);
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
});
const deleteSelectedProject = () => {
    confirmDelete.value = false;
    loading();
    const deletePromises = state.selected.map((project) =>
        deleteProject(project)
    );
    Promise.all(deletePromises).finally(() => {
        search();
    });
};
const setDelete = (id) => {
    state.selected = [id];
    confirmDelete.value = true;
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
const setSort = (field) => {
    if (state.order_by == field && state.sort == "asc") {
        state.sort = "desc";
    } else if (state.order_by == field && state.sort == "desc") {
        state.order_by = null;
        state.sort = null;
    } else {
        state.order_by = field;
        state.sort = "asc";
    }
    search();
};
const handleSelect = (e, id) => {
    if (e.target.checked) {
        state.selected = [...state.selected, id];
    } else {
        state.selected = state.selected.filter((e) => e != id);
    }
};
const handleSelectAll = (e) => {
    if (e.target.checked) {
        state.selected = state.data.map((e) => e.id);
    } else {
        state.selected = [];
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
const doSearch = () => {
    state.page = 1;
    search();
};
const search = () => {
    state.selected = [];
    loading();
    let filter = {
        search: state.search,
        order_by: state.order_by,
        sort: state.sort,
        status: state.status.join(","),
        deposit: state.deposit.join(","),
        action: state.action.join(","),
        invoice: state.invoice.join(","),
        po: state.po.join(","),
        start_date: state.start_date,
        end_date: state.end_date,
        page: state.page,
        limit: state.limit,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem("prj", JSON.stringify(filter));

    getProjectList(filter).then((r) => {
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
            "Invoice Status": e?.invoice_status?.replace(/./, (c) =>
                c.toUpperCase()
            ),
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

    state.export = data;
};
onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("prj");
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
        localStorage.removeItem("prj");
    }
    search();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Projects
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
                                ? 'max-h-400px opacity-100 scale-100'
                                : 'max-h-0 opacity-30 scale-10 overflow-hidden'
                        "
                    >
                        <div
                            class="w-400px bg-white rounded-lg shadow-2xl border"
                        >
                            <div class="w-full p-4 flex text-left">
                                <div class="w-160px border-r p-2 pr-4">
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Status</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="new"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes('new')
                                            "
                                        />
                                        <span>New</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="delivered"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes(
                                                    'delivered'
                                                )
                                            "
                                        />
                                        <span>Delivered</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="ready"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes('ready')
                                            "
                                        />
                                        <span>Ready to Deliver</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="partial"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes('partial')
                                            "
                                        />
                                        <span>Partial Delivery</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="cancel"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes('cancel')
                                            "
                                        />
                                        <span>Cancel</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="production"
                                            @input="
                                                (e) =>
                                                    handleSetFilter(e, 'status')
                                            "
                                            :checked="
                                                state.status.includes(
                                                    'production'
                                                )
                                            "
                                        />
                                        <span>On Production</span>
                                    </label>
                                    <div class="h-2"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >PO Status</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="true"
                                            @input="
                                                (e) => handleSetSingle(e, 'po')
                                            "
                                            :checked="state.po === true"
                                        />
                                        <span>Sudah Ada PO</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="false"
                                            @input="
                                                (e) => handleSetSingle(e, 'po')
                                            "
                                            :checked="state.po === false"
                                        />
                                        <span>Dalam Proses</span>
                                    </label>
                                    <div class="h-2"></div>
                                    <!-- <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >PO Action</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="overdue"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(e, 'action')
                                            "
                                            :checked="
                                                state.action.includes('overdue')
                                            "
                                        />
                                        <span>Overdue</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="today"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(e, 'action')
                                            "
                                            :checked="
                                                state.action.includes('today')
                                            "
                                        />
                                        <span>Today</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="future"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(e, 'action')
                                            "
                                            :checked="
                                                state.action.includes('future')
                                            "
                                        />
                                        <span>Future</span>
                                    </label> -->
                                </div>
                                <div class="flex-1 p-2 pl-4">
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >PO Date</strong
                                    >
                                    <label
                                        class="flex items-start justify-start gap-2 my-2 text-sm w-full flex-col"
                                    >
                                        <span>Start Date</span>
                                        <input
                                            type="date"
                                            class="border rounded h-35px w-full px-2"
                                            onfocus="this.showPicker()"
                                            v-model="state.start_date"
                                        />
                                    </label>
                                    <label
                                        class="flex items-start justify-start gap-2 my-2 text-sm w-full flex-col"
                                    >
                                        <span>End Date</span>
                                        <input
                                            type="date"
                                            class="border rounded h-35px w-full px-2"
                                            onfocus="this.showPicker()"
                                            v-model="state.end_date"
                                        />
                                    </label>
                                    <div class="h-2"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Invoice</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="sent"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(
                                                        e,
                                                        'invoice'
                                                    )
                                            "
                                            :checked="
                                                state.invoice.includes('sent')
                                            "
                                        />
                                        <span>Sent</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="progress"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(
                                                        e,
                                                        'invoice'
                                                    )
                                            "
                                            :checked="
                                                state.invoice.includes(
                                                    'progress'
                                                )
                                            "
                                        />
                                        <span>Progress</span>
                                    </label>
                                    <div class="h-2"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Project Type</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="deposit"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(
                                                        e,
                                                        'deposit'
                                                    )
                                            "
                                            :checked="
                                                state.deposit.includes(
                                                    'deposit'
                                                )
                                            "
                                        />
                                        <span>Deposit</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="nondeposit"
                                            @input="
                                                (e) =>
                                                    handleSetSingle(
                                                        e,
                                                        'deposit'
                                                    )
                                            "
                                            :checked="
                                                state.deposit.includes(
                                                    'nondeposit'
                                                )
                                            "
                                        />
                                        <span>Non Deposit</span>
                                    </label>
                                    <div class="h-2"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Result Limit</strong
                                    >
                                    <br />
                                    <select
                                        v-model="state.limit"
                                        class="w-full border rounded p-1"
                                    >
                                        <option value="1">1</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="500">500</option>
                                    </select>
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
                <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('print')"
                    v-if="user.position !== 'delivery'"
                >
                    <i class="ri-printer-fill text-xl"></i>
                    <strong>Print</strong>
                </button>
                <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#1C8B54] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('excel')"
                    v-if="user.position !== 'delivery'"
                >
                    <i class="ri-file-excel-2-fill text-xl"></i>
                    <strong>Excel</strong>
                </button>
                <button
                    v-if="
                        user.position == 'marketing' || user.position == 'admin'
                    "
                    class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    @click="() => router.push('/desktop/projects/add')"
                >
                    <i class="ri-add-line text-xl"></i>
                    <strong>Add Project</strong>
                </button>
                <button
                    v-if="
                        user.position == 'marketing' || user.position == 'admin'
                    "
                    v-show="state.selected.length"
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-red-500 hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="confirmDelete = true"
                >
                    <i class="ri-delete-bin-line text-xl"></i>
                    <strong>Delete Selected</strong>
                </button>
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
                                <label class="flex items-center justify-center">
                                    <input
                                        type="checkbox"
                                        @input="handleSelectAll"
                                    />
                                </label>
                            </th>

                            <th class="p-1">
                                <button
                                    @click="() => setSort('job_number')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Job Number</span
                                    >
                                    <i
                                        :class="
                                            state.order_by === 'job_number'
                                                ? state.sort === 'asc'
                                                    ? 'opacity-100'
                                                    : 'opacity-100 rotate-180'
                                                : 'opacity-30'
                                        "
                                        class="ri-arrow-down-line text-lg font-thin transform"
                                    ></i>
                                </button>
                            </th>
                            <th class="p-1">
                                <button
                                    @click="() => setSort('client_po_date')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4">PO</span>
                                    <i
                                        :class="
                                            state.order_by === 'client_po_date'
                                                ? state.sort === 'asc'
                                                    ? 'opacity-100'
                                                    : 'opacity-100 rotate-180'
                                                : 'opacity-30'
                                        "
                                        class="ri-arrow-down-line text-lg font-thin transform"
                                    ></i>
                                </button>
                            </th>
                            <th class="p-1">
                                <button
                                    @click="() => setSort('client_company')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4 group"
                                        >Client</span
                                    >
                                    <i
                                        :class="
                                            state.order_by === 'client_company'
                                                ? state.sort === 'asc'
                                                    ? 'opacity-100'
                                                    : 'opacity-100 rotate-180'
                                                : 'opacity-30'
                                        "
                                        class="ri-arrow-down-line text-lg font-thin transform"
                                    ></i>
                                </button>
                            </th>
                            <th class="p-1">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Invoice</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-160px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Product</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-100px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Price</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-50px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Deposit</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-120px">
                                <button
                                    @click="() => setSort('status')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >Status</span
                                    >
                                    <i
                                        :class="
                                            state.order_by === 'status'
                                                ? state.sort === 'asc'
                                                    ? 'opacity-100'
                                                    : 'opacity-100 rotate-180'
                                                : 'opacity-30'
                                        "
                                        class="ri-arrow-down-line text-lg font-thin transform"
                                    ></i>
                                </button>
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
                            <td class="p-1 w-40px min-w-40px">
                                <label class="flex items-center justify-center">
                                    <input
                                        type="checkbox"
                                        :checked="
                                            state.selected.includes(row.id)
                                        "
                                        @input="(e) => handleSelect(e, row.id)"
                                    />
                                </label>
                            </td>

                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        {{ row.job_number }}
                                        <span class="text-xs text-gray-400">
                                            {{ row.title }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                >
                                    {{ row.client_po_number ?? "" }}
                                    <span
                                        v-if="!row.client_po_number"
                                        class="flex items-center gap-2"
                                    >
                                        On Process

                                        <Popover
                                            v-if="row?.meta?.po_deadline"
                                            :content="
                                                row.meta?.po_deadline?.notes.replace(
                                                    /\r\n/g,
                                                    '<br />'
                                                )
                                            "
                                            :title="
                                                'PO Deadline: '+dayjs(row?.po_deadline).format(
                                                    'DD MMM YYYY'
                                                )
                                            "
                                        >
                                            <i
                                                v-if="
                                                    dayjs().isAfter(
                                                        row?.meta?.po_deadline
                                                            ?.reminder
                                                    )
                                                "
                                                class="ri-alert-fill text-xl text-red-500"
                                            ></i>
                                            <i
                                                v-else
                                                class="ri-information-fill text-xl text-blue-500"
                                            ></i>
                                        </Popover>
                                    </span>
                                    <span
                                        class="text-xs text-gray-400"
                                        v-if="row.client_po_number"
                                    >
                                        <span class="text-gray-400">
                                            {{
                                                row.client_po_date
                                                    ? dayjs(
                                                          row.client_po_date
                                                      ).format("DD MMM YYYY")
                                                    : "On Process"
                                            }}
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col"
                                    >
                                        <div
                                            class="w-180px truncate"
                                            :title="row.client_company"
                                        >
                                            {{ row.client_company }}
                                        </div>
                                        <span class="text-xs text-gray-400">
                                            PIC: {{ row.client_pic_name }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <span
                                        v-if="
                                            !row.invoice &&
                                            ((!row?.is_real &&
                                                row?.is_po_deposit) ||
                                                (row?.is_real &&
                                                    !row?.is_po_deposit))
                                        "
                                        class="flex items-center gap-2"
                                    >
                                        On Progress
                                    </span>
                                    <div
                                        v-if="
                                            row.invoice &&
                                            ((!row?.is_real &&
                                                row?.is_po_deposit) ||
                                                (row?.is_real &&
                                                    !row?.is_po_deposit))
                                        "
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        <div class="">
                                            {{
                                                row.invoice?.invoice_number ??
                                                ""
                                            }}
                                        </div>
                                        <div class="flex gap-1">
                                            <span class="text-xs text-gray-400">
                                                <span
                                                    class="capitalize"
                                                    :class="
                                                        row.invoice_status ==
                                                        'progress'
                                                            ? 'text-orange-500'
                                                            : 'text-green-500'
                                                    "
                                                    >{{
                                                        row?.invoice_status
                                                    }}</span
                                                >
                                            </span>
                                            <span
                                                class="capitalize text-xs text-gray-400"
                                                >{{
                                                    row.invoice
                                                        ? " (" +
                                                          dayjs(
                                                              row.invoice
                                                                  ?.invoice_date
                                                          ).format(
                                                              "DD MMM YYYY"
                                                          ) +
                                                          ")"
                                                        : ""
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <Popover
                                        v-if="row?.meta?.invoice_deadline"
                                        :content="
                                            row.meta?.invoice_deadline?.notes.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                        :title="'Finance Note'"
                                    >
                                        <i
                                            v-if="
                                                row?.status == 'delivered' && row?.invoice_status != 'sent'
                                            "
                                            class="ri-alert-fill text-xl text-red-500"
                                        ></i>
                                        <i
                                            v-else
                                            class="ri-information-fill text-xl text-blue-500"
                                        ></i>
                                    </Popover>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-start justify-center p-1 flex-col"
                                >
                                    <div
                                        class="w-150px truncate"
                                        :title="row.products_data[0]?.name"
                                    >
                                        {{ row.products_data[0]?.name }}
                                    </div>
                                    <span
                                        class="text-xs text-gray-400"
                                        v-if="row.products_data.length > 1"
                                    >
                                        +{{ row.products_data.length - 1 }}
                                        other product(s)
                                    </span>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-start justify-center p-1 flex-col"
                                >
                                    <div
                                        class="w-150px truncate"
                                        :title="row.products_data[0]?.name"
                                        v-if="user.position !== 'delivery'"
                                    >
                                        Rp. {{ nom(row?.total_price ?? 0) }}
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                        v-if="row.is_po_deposit && !row.is_real"
                                        :title="`Deposit Non Actual`"
                                    >
                                        <span class="text-2xl text-green-600">
                                            <i
                                                class="ri-money-dollar-circle-fill"
                                            ></i>
                                        </span>
                                    </div>
                                    <div
                                        v-else-if="
                                            row.is_real && row.is_po_deposit
                                        "
                                        title="Deposit Actual"
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        <span class="text-2xl text-green-600">
                                            <i class="ri-circle-fill"></i>
                                        </span>
                                    </div>
                                    <div
                                        v-else
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                        title="Project"
                                    >
                                        <span class="text-2xl text-blue-600">
                                            <i class="ri-box-2-fill"></i>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex gap-2 items-center justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex"
                                        v-if="
                                            (row?.is_real &&
                                                row?.is_po_deposit) ||
                                            (row?.is_real &&
                                                !row?.is_po_deposit)
                                        "
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

                                    <Popover
                                        v-if="
                                            row?.meta?.production_deadline &&
                                            row.status == 'production'
                                        "
                                        :content="
                                            row.meta?.production_deadline?.notes.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                        :title="
                                            'Production Deadline: '+dayjs(
                                                row?.production_deadline
                                            ).format('DD MMM YYYY')
                                        "
                                    >
                                        <i
                                            v-if="
                                                dayjs().isAfter(
                                                    row?.meta
                                                        ?.production_deadline
                                                        ?.reminder
                                                )
                                            "
                                            class="ri-alert-fill text-xl text-red-500"
                                        ></i>
                                        <i
                                            v-else
                                            class="ri-information-fill text-xl text-blue-500"
                                        ></i>
                                    </Popover>
                                    <Popover
                                        v-if="
                                            row?.meta?.delivery_deadline &&
                                            row.status == 'ready'
                                        "
                                        :content="
                                            row.meta?.delivery_deadline?.notes.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                        :title="
                                            'Delivery Deadline: '+dayjs(
                                                row?.delivery_deadline
                                            ).format('DD MMM YYYY')
                                        "
                                    >
                                        <i
                                            v-if="
                                                dayjs().isAfter(
                                                    row?.meta?.delivery_deadline
                                                        ?.reminder
                                                )
                                            "
                                            class="ri-alert-fill text-xl text-red-500"
                                        ></i>
                                        <i
                                            v-else
                                            class="ri-information-fill text-xl text-blue-500"
                                        ></i>
                                    </Popover>
                                </div>
                            </td>
                            <td class="p-1 w-60px">
                                <div class="flex items-center justify-end p-1">
                                    <router-link
                                        :to="'/desktop/threads/' + row.id"
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Threads"
                                        >
                                            <i class="ri-chat-history-line"></i>
                                        </div>
                                    </router-link>
                                    <!-- <router-link
                                        :to="
                                            '/desktop/projects/finance/' +
                                            row.id
                                        "
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Invoice Progress"
                                        >
                                            <i
                                                class="ri-secure-payment-line"
                                            ></i>
                                        </div>
                                    </router-link>
                                    <router-link
                                        :to="
                                            '/desktop/projects/followup/' +
                                            row.id
                                        "
                                        v-if="!row.is_po_deposit"
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Marketing Follow Up"
                                        >
                                            <i
                                                class="ri-customer-service-2-line"
                                            ></i>
                                        </div>
                                    </router-link> -->
                                    <router-link
                                        :to="'/desktop/projects/edit/' + row.id"
                                        v-if="
                                            user.position == 'marketing' ||
                                            user.position == 'admin'
                                        "
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Update"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </div>
                                    </router-link>
                                    <button
                                        @click="setDelete(row.id)"
                                        class="text-xl px-2 py-1 text-red-500 hover:bg-gray-100 rounded"
                                        title="Delete"
                                        v-if="
                                            (user.position == 'marketing' ||
                                                user.position == 'admin') &&
                                            !row.is_po_deposit
                                        "
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-between px-6 py-2 items-center">
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
            </div>
        </div>
    </div>
    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected project?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedProject"
    >
    </Confirm>

    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
