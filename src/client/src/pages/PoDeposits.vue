<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import BottomSheetAction from "../components/BottomSheetAction.vue";
import Confirm from "../components/Confirm.vue";
import { loading } from "../services/router";
import {
    deleteProject,
    getProjectList,
    nom,
    goto,
    sortByProperty,
    getPodepositList,
    deletePodeposit,
    currentUser,
} from "../services/service";
import { useRoute } from "vue-router";
import BottomsheetDepositFilter from "../components/BottomsheetDepositFilter.vue";
import Table from "../components/Table.vue";
const user = currentUser.user?.user;
const confirmDelete = ref(false);
const route = useRoute();
const handleDelete = () => {
    loading();
    deletePodeposit(state.selected?.id).then((r) => {
        if (r.code === 200) {
            confirmDelete.value = false;
            state.selected = null;
            search();
        } else {
            alertShowError.value = true;
            state.selected = null;
        }
        loading(false);
    });
};
const state = reactive({
    data: [],
    meta: {},
    selected: null,
    filter_open: false,
    search: "",
    status: [],
    start_date: null,
    end_date: null,
    page: 1,
    limit: 10,
    export: null,
    export_mode: "print",
});
const handleFilter = (e) => {
    state.page = 1;
    state.status = e.status;
    state.start_date = e.start_date;
    state.end_date = e.end_date;
    state.limit = e.limit;
    state.filter_open = false;
    search();
};
const handleClearFilter = () => {
    state.status = [];
    state.start_date = null;
    state.end_date = null;
    state.filter_open = false;
    state.limit = 10;
    search();
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
const doSearch = () => {
    state.page = 1;
    search();
};
const search = () => {
    // state.page = 1;
    // state.selected = null;
    loading();
    let filter = {
        search: state.search,
        status: state.status.join(","),
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
    };
    let data = state.data.map((e) => ({
        "PO Number": e.client_po_number ?? "Dalam Proses",
        "PO Date": e.client_po_date
            ? dayjs(e.client_po_date).format("DD MMM YYYY")
            : "Dalam Proses",
        "Job Number": e.job_number,
        Client: e.client_company,
        "Client's PIC": e.client_pic_name,
        "Pelangi's PIC": e.pic_name,
        "PO Action": e.po_actions?.followup_date ?? e.po_actions?.planned_date,
        "Invoice Number": e.invoice?.invoice_number,
        "Invoice Date": e.invoice?.invoice_date,
        "Invoice Status": e.po_deposit_data?.invoice_status?.replace(/./, (c) =>
            c.toUpperCase()
        ),
        "Invoice PIC": e.invoice_progress?.pic?.replace(/./, (c) =>
            c.toUpperCase()
        ),
        Deposit: e.is_po_deposit ? "Yes" : "No",
        // "Shipping Vendor": e.shipping_vendors_data?.name,
        Products: e.products_data
            .map((ee) => `${ee.name} (${nom(ee.quantity)})`)
            .join("<br>"),
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
    doSearch();
});
const submit = (e) => {
    e.preventDefault();
    doSearch();
};
</script>

<template>
    <div class="flex-1 flex flex-col min-h-0 h-full">
        <div
            class="flex shadow-md z-2 shadow-gray-100 bg-white px-2 py-1 gap-1"
        >
            <form
                @submit="submit"
                class="flex-1 flex items-center rounded-full overflow-hidden bg-gray-100 relative"
            >
                <input
                    type="search"
                    placeholder="Search..."
                    name=""
                    id=""
                    class="h-9 flex-1 text-light bg-transparent text-black rounded-full px-4 w-full"
                    v-model="state.search"
                />
                <button
                    class="h-9 w-9 flex items-center justify-center text-app-500 absolute right-1"
                >
                    <i class="ri-search-line"></i>
                </button>
            </form>
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="state.filter_open = true"
            >
                <i class="ri-filter-line"></i>
            </button>
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('print')"
                v-if="user.position != 'delivery'"
            >
                <i class="ri-printer-line"></i>
            </button>
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="prepareExport('excel')"
                v-if="user.position != 'delivery'"
            >
                <i class="ri-file-excel-2-line"></i>
            </button>
        </div>
        <div class="flex-1 overflow-auto">
            <div
                class="min-h-full min-w-full flex flex-col bg-gray-50"
                v-if="state.data.length"
            >
                <div
                    class="rounded-none bg-white border-b-2"
                    v-for="item in state.data"
                    :key="item.id"
                    @click="state.selected = item"
                >
                    <!-- <router-link :to="'/details/' + item.id"> -->
                    <div class="flex justify-between px-4 pt-2 items-center">
                        <div class="flex gap-2 items-center h-6">
                            <span
                                class="text-xs text-blue-gray-500 font-semibold"
                                v-show="item.client_po_number !== null"
                                >#PO {{ item.client_po_number }}</span
                            >
                            <span
                                class="text-xs text-blue-gray-500 font-semibold"
                                v-show="item.client_po_number === null"
                                >#PO Dalam Proses</span
                            >
                            <i
                                v-show="item.client_po_number === null"
                                class="ri-error-warning-fill text-lg text-app-500"
                            ></i>
                        </div>
                        <div class="flex gap-2 items-center h-7">
                            <span class="text-xs text-black"
                                >#JOB {{ item.job_number }}</span
                            >
                        </div>
                    </div>
                    <div class="flex justify-between gap-1 px-4 items-start">
                        <div class="flex w-full flex-col">
                            <span class="text-app-900 text-xs text-left"
                                >by {{ item.client_company }}</span
                            >
                            <span class="text-xs text-gray-500 text-left">
                                PIC : {{ item.client_pic_name }}
                            </span>
                        </div>
                        <div class="flex gap-1 items-center">
                            <div
                                class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
                                :class="
                                    item?.status === 'open'
                                        ? 'bg-[#4DC8E3]'
                                        : item?.status === 'close'
                                        ? 'bg-[#E44545]'
                                        : ''
                                "
                            >
                                {{ item?.status }}
                            </div>
                            <span
                                class="text-xl"
                                :class="
                                    item.is_po_deposit
                                        ? 'text-green-600'
                                        : 'text-gray-200'
                                "
                            >
                                <i class="ri-money-dollar-circle-fill"></i>
                            </span>
                        </div>
                    </div>
                    <div class="border-b w-full mt-2"></div>
                    <div
                        class="px-4 py-2 flex"
                        v-if="user?.position !== 'delivery'"
                    >
                        <div class="flex w-full gap-1">
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Budget</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{ nom(item.budget ?? 0) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Spent</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{ nom(item.expense ?? 0) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Balance</span
                                >
                                <div class="text-xs text-black">
                                    <span>{{ nom(item.balance ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- </router-link> -->
                </div>
            </div>
            <div
                class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                v-else
            >
                <i class="ri-inbox-line text-100px text-gray-200"></i>
                <span class="text-sm font-medium text-gray-400"
                    >No PO Available</span
                >
            </div>
        </div>
        <div
            class="h-40px bg-gray-100 shadow-lg w-full border-t flex items-center px-1"
        >
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page <= 1
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="prevPage"
            >
                <i class="ri-skip-left-line"></i>
                <span class="text-xs">Prev</span>
            </div>
            <div class="flex-1 items-center justify-center flex text-sm">
                Page {{ state.page }} of {{ state.meta?.total_pages || 1 }}
            </div>
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page >= state.meta?.total_pages
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="nextPage"
            >
                <span class="text-xs">Next</span>
                <i class="ri-skip-right-line"></i>
            </div>
        </div>
    </div>

    <div
        class="fixed bottom-12 right-2 w-12 h-12 bg-red-500 rounded-full shadow flex items-center justify-center text-white text-2xl font-bold"
        @click="goto('/podeposits/add')"
        v-if="user.position == 'marketing' || user.position == 'admin'"
    >
        <i class="ri-add-line"></i>
    </div>
    <BottomSheetAction
        :show="state.selected != null"
        @hide="state.selected = null"
        @action="(e) => (e == 'delete' ? (confirmDelete = true) : null)"
        :actions="
            user?.position == 'marketing'
                ? [
                      {
                          title: 'Edit',
                          link: '/podeposits/edit/' + state.selected?.id,
                          icon: 'ri-pencil-line',
                      },
                      {
                          title: 'Delete',
                          action: 'delete',
                          color: 'red',
                          icon: 'ri-delete-bin-line',
                      },
                  ]
                : [
                      {
                          title: 'View',
                          link: '/podeposits/edit/' + state.selected?.id,
                          icon: 'ri-eye-line',
                      },
                  ]
        "
    />
    <BottomsheetDepositFilter
        :show="state.filter_open"
        :status="state.status"
        :start_date="state.start_date"
        :end_date="state.end_date"
        :limit="state.limit"
        @hide="state.filter_open = false"
        @apply="handleFilter"
        @clear="handleClearFilter"
    ></BottomsheetDepositFilter>

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected po?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="(confirmDelete = false), (state.selected = null)"
        @fire="handleDelete"
    />
    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />
</template>
