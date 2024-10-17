<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import { useRoute, useRouter } from "vue-router";
import {
    getProjectList,
    deleteProject,
    nom,
    getPodepositList,
    deletePodeposit,
    currentUser,
} from "../services/service";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Table from "../components/Table.vue";
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
    status: [],
    po: null,
    start_date: null,
    end_date: null,
    page: 1,
    limit: 10,
    export: null,
    export_mode: "print",
});
const deleteSelectedProject = () => {
    confirmDelete.value = false;
    loading();
    const deletePromises = state.selected.map((project) =>
        deletePodeposit(project)
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
const handleSetStatus = (e) => {
    if (e.target.checked) {
        state.status = [...state.status, e.target.value];
    } else {
        state.status = state.status.filter((d) => d != e.target.value);
    }
};
const handleSetPO = (e) => {
    if (e.target.checked) {
        state.po = e.target.value == "true";
    } else {
        state.po = null;
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
    state.po = null;
    state.start_date = null;
    state.end_date = null;
    state.page = 1;
    state.limit = 10;
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
        po: state.po,
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
    localStorage.setItem("pod", JSON.stringify(filter));
    getPodepositList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
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
        open: "Open Order",
        close: "Close Order",
    };
    let data = state.data.map((e) => ({
        "PO Date": dayjs(e.client_po_date).format("DD MMM YYYY"),
        "Job Number": e.job_number,
        Client: e.client_company,
        "Client's PIC": e.client_pic_name,
        "PO Number": e.client_po_number,
        "Pelangi's PIC": e.pic_name,
        Budget: e.budget,
        Spent: e.expense,
        Balance: e.balance,

        Status: status[e.status],
    }));

    state.export = data;
};
onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("pod");
        if (flt) {
            flt = JSON.parse(flt);
            state.filter_open = flt?.filter_open ?? null;
            state.search = flt?.search ?? null;
            state.order_by = flt?.order_by ?? null;
            state.sort = flt?.sort ?? null;
            state.status = flt?.status?.split(",") ?? [];
            state.po = flt?.po ?? null;
            state.start_date = flt?.start_date ?? null;
            state.end_date = flt?.end_date ?? null;
            state.page = flt?.page ?? null;
            state.limit = flt?.limit ?? null;
        }
    } else {
        localStorage.removeItem("pod");
    }
    search();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            PO Deposit
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
                                            value="open"
                                            @input="handleSetStatus"
                                            :checked="
                                                state.status.includes('open')
                                            "
                                        />
                                        <span>Open Order</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="close"
                                            @input="handleSetStatus"
                                            :checked="
                                                state.status.includes('close')
                                            "
                                        />
                                        <span>Closed</span>
                                    </label>
                                    <div class="h-2"></div>
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
                    v-if="
                        user.position == 'marketing' ||
                        user.position == 'admin' ||
                        user.position == 'finance'
                    "
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('print')"
                >
                    <i class="ri-printer-fill text-xl"></i>
                    <strong>Print</strong>
                </button>
                <button
                    v-if="
                        user.position == 'marketing' ||
                        user.position == 'admin' ||
                        user.position == 'finance'
                    "
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#1C8B54] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('excel')"
                >
                    <i class="ri-file-excel-2-fill text-xl"></i>
                    <strong>Excel</strong>
                </button>
                <button
                    v-if="
                        user.position == 'marketing' || user.position == 'admin'
                    "
                    class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    @click="() => router.push('/desktop/podeposits/add')"
                >
                    <i class="ri-add-line text-xl"></i>
                    <strong>Add PO Deposit</strong>
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
                    @submit.prevent="search"
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
                                    @click="() => setSort('client_po_date')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >PO Date</span
                                    >
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
                                    @click="() => setSort('client_po_number')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4"
                                        >PO Number</span
                                    >
                                    <i
                                        :class="
                                            state.order_by ===
                                            'client_po_number'
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
                            <th class="p-1 w-70px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Budget</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-70px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Spent</span
                                    >
                                </div>
                            </th>
                            <th class="p-1 w-70px">
                                <div
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                >
                                    <span class="font-bold leading-4"
                                        >Balance</span
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
                                    class="flex items-center justify-start p-1 whitespace-nowrap"
                                >
                                    {{
                                        row.client_po_date
                                            ? dayjs(row.client_po_date).format(
                                                  "DD MMM YYYY"
                                              )
                                            : "On Process"
                                    }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        {{
                                            row.client_po_number
                                                ? row.client_po_number
                                                : "On Process"
                                        }}
                                    </div>
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        {{ row.job_number }}
                                    </div>
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
                                    class="flex items-center justify-start p-1 whitespace-nowrap"
                                    v-if="user.position !== 'delivery'"
                                >
                                    Rp. {{ nom(row.budget ?? 0) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1 whitespace-nowrap"
                                    v-if="user.position !== 'delivery'"
                                >
                                    Rp. {{ nom(row.expense ?? 0) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1 whitespace-nowrap"
                                    v-if="user.position !== 'delivery'"
                                >
                                    Rp. {{ nom(row.balance ?? 0) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex flex-col items-start justify-start p-1"
                                >
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap capitalize"
                                        :class="
                                            row.status === 'open'
                                                ? 'bg-[#6FD669]'
                                                : row.status === 'close'
                                                ? 'bg-[#E44545]'
                                                : ''
                                        "
                                        >{{ row.status }} Order</span
                                    >
                                </div>
                            </td>
                            <td class="p-1 w-60px">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <router-link
                                        :to="
                                            '/desktop/podeposits/edit/' + row.id
                                        "
                                    >
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Update"
                                            v-if="
                                                user.position == 'marketing' ||
                                                user.position == 'admin'
                                            "
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </div>
                                        <div
                                            class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="View"
                                            v-else
                                        >
                                            <i class="ri-eye-line"></i>
                                        </div>
                                    </router-link>
                                    <button
                                        v-if="
                                            user.position == 'marketing' ||
                                            user.position == 'admin'
                                        "
                                        @click="setDelete(row.id)"
                                        class="text-xl px-2 py-1 text-red-500 hover:bg-gray-100 rounded"
                                        title="Delete"
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
        content="Are you sure want to delete selected po deposit?"
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
