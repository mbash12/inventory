<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import Table from "../components/Table.vue";
import { useRoute, useRouter } from "vue-router";
import { getProjectList, deleteProject, nom } from "../services/service";
import { loading } from "../services/router";

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
        delivery: true,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem("del", JSON.stringify(filter));
    getProjectList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }
    });
};
const prepareExport = (mode) => {
    state.export_mode = mode;
    let status = {
        cancel: "Cancel",
        ready: "Ready To Deliver",
        partial: "Partial Delivery",
        delivered: "Delivered",
    };
    let data = state.data.map((e) => ({
        "Job Number": e.job_number,
        "PO Number": e.client_po_number ?? "Dalam Proses",
        "PO Date": e.client_po_date
            ? dayjs(e.client_po_date).format("DD MMM YYYY")
            : "Dalam Proses",
        "Pelangi's PIC": e.pic_name,
        Client: e.client_company,
        "Client's PIC": e.client_pic_name,
        Products: e.products_data && Array.isArray(e.products_data)
            ? e.products_data
                .map((ee) => `${ee.name} (${nom(ee.quantity)})`)
                .join("<br>")
            : "",
        Stored: nom(e.stored ?? 0),
        Delivered: nom(e.delivered ?? 0),
        Status: status[e.status],
    }));

    state.export = data;
};
onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("del");
        if (flt) {
            flt = JSON.parse(flt);
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
        localStorage.removeItem("del");
    }

    search();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Delivery Report
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
                                            value="delivered"
                                            @input="handleSetStatus"
                                        />
                                        <span>Delivered</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="ready"
                                            @input="handleSetStatus"
                                        />
                                        <span>Ready to Deliver</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="partial"
                                            @input="handleSetStatus"
                                        />
                                        <span>Partial Delivery</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="cancel"
                                            @input="handleSetStatus"
                                        />
                                        <span>Cancel</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="production"
                                            @input="handleSetStatus"
                                        />
                                        <span>On Production</span>
                                    </label>
                                    <div class="h-4"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >PO</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="haspo"
                                            @input="handleSetPO"
                                            :checked="state.po === true"
                                        />
                                        <span>Sudah Ada PO</span>
                                    </label>
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full"
                                    >
                                        <input
                                            type="checkbox"
                                            value="onprocess"
                                            @input="handleSetPO"
                                            :checked="state.po === false"
                                        />
                                        <span>Dalam Proses</span>
                                    </label>
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

                                    <div class="h-4"></div>
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
                >
                    <i class="ri-printer-fill text-xl"></i>
                    <strong>Print</strong>
                </button>
                <button
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#1C8B54] hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="prepareExport('excel')"
                >
                    <i class="ri-file-excel-2-fill text-xl"></i>
                    <strong>Excel</strong>
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
            <div class="overflow-x-auto">
                <table class="w-full h-full border-b">
                    <thead
                        class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px"
                    >
                        <tr>
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
                                        >Product</span
                                    >
                                </div>
                            </th>

                            <th class="p-1">
                                <button
                                    @click="() => setSort('stored')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4 group"
                                        >Stored</span
                                    >
                                    <i
                                        :class="
                                            state.order_by === 'stored'
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
                                    @click="() => setSort('delivered')"
                                    class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                                >
                                    <span class="font-bold leading-4 group"
                                        >Delivered</span
                                    >
                                    <i
                                        :class="
                                            state.order_by === 'delivered'
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
                                    {{ row.client_po_number ?? "On Process" }}
                                    <span class="text-xs text-gray-400">
                                        <span
                                            class="text-gray-400"
                                            v-if="row.client_po_number"
                                        >
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
                                    class="flex items-start justify-center p-1 flex-col"
                                >
                                    <div
                                        class="w-150px truncate"
                                        :title="row.products_data && row.products_data[0] ? row.products_data[0].name : ''"
                                    >
                                        {{ row.products_data && row.products_data[0] ? row.products_data[0].name : '' }}
                                    </div>
                                    <span
                                        class="text-xs text-gray-400"
                                        v-if="row.products_data && row.products_data.length > 1"
                                    >
                                        +{{ row.products_data.length - 1 }}
                                        other product(s)
                                    </span>
                                </div>
                            </td>

                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <strong>{{ nom(row.stored ?? 0) }}</strong
                                    >/{{ nom(row.total ?? 0) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex items-center justify-start p-1"
                                >
                                    <strong>{{
                                        nom(row.delivered ?? 0)
                                    }}</strong
                                    >/{{ nom(row.total ?? 0) }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div
                                    class="flex flex-col items-start justify-start p-1"
                                >
                                    <span
                                        class="rounded-full py-1 px-2 text-11px font-medium text-white whitespace-nowrap"
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
                            </td>
                            <td class="p-1 w-60px">
                                <div
                                    class="flex items-center justify-start py-1 px-4"
                                >
                                    <router-link
                                        :to="
                                            '/desktop/delivery-report/logs/' +
                                            row.id
                                        "
                                    >
                                        <div
                                            class="text-xl px-3 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                            title="Details"
                                        >
                                            <i class="ri-file-list-3-line"></i>
                                        </div>
                                    </router-link>
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
    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
