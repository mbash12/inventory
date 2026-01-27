<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import LightImage from "../components/LightImage.vue";
import { useRoute, useRouter } from "vue-router";
import { getInventoryList, getWarhouseList, nom } from "../services/service";
import { loading } from "../services/router";
import Table from "../components/Table.vue";
const router = useRouter();
const state = reactive({
    warehouses:null,
    data: [],
    meta: {},
    filter_open: false,
    search: "",
    selected: [],
    order_by: null,
    warehouse:[],
    sort: null,
    po: null,
    page: 1,
    limit:10,
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
    state.warehouse = [];
    state.po = null;
    state.page = 1;
    state.limit = 10;
    search();
};

const handleSetWarehouse = (e) => {
    if (e.target.checked) {
        state.warehouse = [...state.warehouse, e.target.value];
    } else {
        state.warehouse = state.warehouse.filter((d) => d != e.target.value);
    }
};
const search = () => {
    state.selected = [];
    loading();
    let filter = {
        search: state.search,
        order_by: state.order_by,
        sort: state.sort,
        warehouse: state.warehouse.join(","),
        po: state.po,
        page: state.page,
        limit: state.limit,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem('inv',JSON.stringify(filter));
    getInventoryList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }
    });
};

const prepareExport = (mode) => {
  state.export_mode = mode;
  let data = state.data.map((e) => ({
    "Warehouse" : e.warehouse_data.name,
    "Product" : e.product_data.name,
    "Quantity" : nom(e.quantity),
    "Client" : e.project_data.client_company,
    "Client PIC" : e.project_data.client_pic_name,
    "Client PIC" : e.project_data.client_pic_name,
    "PO Number" : e.project_data.client_po_number ?? 'Dalam Proses',
    "Job Number" : e.project_data.job_number,
  }));

  state.export = data;
};
onMounted(() => {
    let flt = localStorage.getItem('inv')
    if(flt){
        flt = JSON.parse(flt)
        state.filter_open = flt?.filter_open ?? null;
        state.search = flt?.search ?? null;
        state.selected = flt?.selected ?? null;
        state.order_by = flt?.order_by ?? null;
        state.sort = flt?.sort ?? null;
        state.warehouse = flt?.warehouse?.split(",") ?? [];
        state.po = flt?.po ?? null;
        state.page = flt?.page ?? null;
        state.limit = flt?.limit ?? null;
    }
    getWarhouseList().then((r)=>{
        if(r.code === 200){
            state.warehouses = r.data.filter(e=>e.storage)
        }
    })
    search();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Inventory
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
                            class="w-280px bg-white rounded-lg shadow-2xl border"
                        >
                            <div class="w-full p-4 flex text-left">
                                <div class="w-full p-2 pr-4">
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Warehouse</strong
                                    >
                                    <label
                                        class="flex items-center justify-start gap-2 my-2 text-sm w-full capitalize"
                                        v-for="(wh, i) in state.warehouses"
                                        :key="i"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="wh.id"
                                            @input="handleSetWarehouse"
                                        />
                                        <span>{{ wh.name }}</span>
                                    </label>
                                    <div class="h-4"></div>
                                    <strong
                                        class="font-semibold text-blue-grayy-700 text-sm"
                                        >Result Limit</strong
                                    >
                                    <br>
                                    <select v-model="state.limit" class="w-full border rounded p-1">
                                        <option value="1">1</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
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
                <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                    <tr>
                        <th class="p-1 pl-4">
                            <button
                                @click="() => setSort('warehouse_name')"
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4"
                                    >Warehouse</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'warehouse_name'
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
                                @click="() => setSort('product_name')"
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4 group"
                                    >Product</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'product_name'
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
                                @click="() => setSort('quantity')"
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4 group"
                                    >Quantity</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'quantity'
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
                                @click="() => setSort('project_data.client_company')"
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
                            <button
                                @click="() => setSort('project_data.client_pic_name')"
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4 group"
                                    >Client PIC</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'client_pic_name'
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
                                @click="() => setSort('project_data.client_po_number')"
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4"
                                    >PO Number</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'client_po_number'
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
                                @click="() => setSort('project_data.job_number')"
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
                    </tr>
                </thead>
                <tbody class="text-13px">
                    <tr
                        class="border-b h-56px"
                        v-for="(row, i) in state.data"
                        :key="i"
                    >
                        <td class="p-1 pl-4">
                            <div
                                class="flex items-center justify-start p-1 capitalize"
                            >
                                {{ row.warehouse_data.name }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-start justify-center p-1 flex-col"
                            >
                                {{ row.product_data.name }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-start justify-center p-1 flex-col"
                            >
                                {{ nom(row.quantity) }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1"
                            >
                                {{ row.project_data.client_company }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1"
                            >
                                {{ row.project_data.client_pic_name }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1"
                            >
                                {{
                                    row.project_data.client_po_number ??
                                    "Dalam Proses"
                                }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1"
                            >
                                {{ row.project_data.job_number }}
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
