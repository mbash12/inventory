<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import BottomSheetAction from "../components/BottomSheetAction.vue";
import Confirm from "../components/Confirm.vue";
import { loading } from "../services/router";
const user = currentUser.user?.user;
import {
    deleteProject,
    getProjectList,
    nom,
    goto,
    sortByProperty,
    currentUser,
} from "../services/service";
import BottomsheetProjectFilter from "../components/BottomsheetProjectFilter.vue";
import Table from "../components/Table.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const confirmDelete = ref(false);
const handleDelete = () => {
    loading();
    deleteProject(state.selected?.id).then((r) => {
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
    order_by: null,
    sort: null,
    status: [],
    po: [],
    po_action: [],
    invoice: [],
    deposit: [],
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
    state.po = e.po;
    state.po_action = e.po_action;
    state.invoice = e.invoice;
    state.deposit = e.deposit;
    state.start_date = e.start_date;
    state.end_date = e.end_date;
    state.limit = e.limit;
    state.filter_open = false;
    search();
};
const handleClearFilter = () => {
    state.status = [];
    state.po = [];
    state.po_action = [];
    state.invoice = [];
    state.deposit = [];
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
        order_by: state.order_by,
        sort: state.sort,
        status: state.status.join(","),
        po: state.po.join(","),
        action: state.po_action.join(","),
        invoice: state.invoice.join(","),
        deposit: state.deposit.join(","),
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
                            <div class="flex items-center flex-wrap my-6px">
                                <span
                                    class="font-medium text-gray-400 text-xs leading-4 pr-2 w-full"
                                    >{{ item.title }}</span
                                >
                                <span
                                    class="font-semibold text-black text-sm leading-4 pr-2"
                                    >{{ item.products_data[0]?.name }}</span
                                >
                                <span
                                    class="text-blue-gray-500 text-xs"
                                    v-if="item.products_data.length > 1"
                                    >+{{
                                        item.products_data.length - 1
                                    }}
                                    other(s)</span
                                >
                            </div>
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
                                    item?.status === 'delivered'
                                        ? 'bg-[#6FD669]'
                                        : item?.status === 'partial'
                                        ? 'bg-[#4DC8E3]'
                                        : item?.status === 'ready'
                                        ? 'bg-[#E5C100]'
                                        : item?.status === 'cancel'
                                        ? 'bg-[#E44545]'
                                        : item?.status === 'production'
                                        ? 'bg-[#3f51b5]'
                                        : item?.status === 'new'
                                        ? 'bg-[#AD58D4]'
                                        : ''
                                "
                            >
                                {{ item?.status }}
                            </div>
                            <span
                                class="text-2xl text-green-600"
                                v-if="item.is_po_deposit && !item.is_real"
                                :title="`Deposit Non Actual`"
                            >
                                <i class="ri-money-dollar-circle-fill"></i>
                            </span>
                            <span
                                class="text-2xl text-green-600"
                                v-else-if="item.is_real && item.is_po_deposit"
                                title="Deposit Actual"
                            >
                                <i class="ri-circle-fill"></i>
                            </span>
                            <span class="text-2xl text-blue-600" v-else>
                                <i class="ri-box-2-fill"></i>
                            </span>
                        </div>
                    </div>
                    <div class="border-b w-full mt-2"></div>
                    <div class="px-4 py-2 flex">
                        <div class="flex w-full gap-1">
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Invoice Number</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{ item.invoice?.invoice_number }}
                                    </span>
                                    <span>
                                        {{
                                            item.invoice
                                                ? dayjs(
                                                      item.invoice?.invoice_date
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Invoice PIC</span
                                >
                                <div class="text-xs text-black capitalize">
                                    <span>
                                        {{ item?.invoice_pic }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Invoice Status</span
                                >
                                <div
                                    class="text-xs font-medium capitalize"
                                    :class="
                                        item?.invoice_status == 'progress'
                                            ? 'text-orange-500'
                                            : 'text-green-500'
                                    "
                                >
                                    <span>{{ item?.invoice_status }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border-b w-full"></div>
                    <div class="px-4 py-2 flex">
                        <div class="flex w-full gap-1">
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >PO Deadline</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{
                                            item.po_deadline
                                                ? dayjs(
                                                      item.po_deadline
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Production Deadline</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{
                                            item.production_deadline
                                                ? dayjs(
                                                      item.production_deadline
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Delivery Deadline</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{
                                            item.delivery_deadline
                                                ? dayjs(
                                                      item.delivery_deadline
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="px-4 py-2 flex">
                        <div class="flex w-full gap-1">
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >PO Action</span
                                >
                                <div
                                    class="text-xs"
                                    :class="
                                        item.planned_action
                                            ? 'text-orange-600'
                                            : 'text-gray-600'
                                    "
                                >
                                    <span>
                                        {{
                                            item.po_actions?.followup_date
                                                ? dayjs(
                                                      item.po_actions
                                                          ?.followup_date
                                                  ).format("DD MMM YYYY")
                                                : item.po_actions?.schedule_date
                                                ? dayjs(
                                                      item.po_actions
                                                          ?.schedule_date
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Invoice Progress</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{
                                            item.invoice_progress
                                                ? dayjs(
                                                      item.invoice_progress
                                                          ?.created_at
                                                  ).format("DD MMM YYYY")
                                                : ""
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col items-start">
                                <span class="text-xs text-blue-gray-500"
                                    >Invoice PIC</span
                                >
                                <div class="text-xs text-black">
                                    <span>
                                        {{ item.invoice_progress?.pic }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div> -->
                    <!-- </router-link> -->
                </div>
            </div>
            <div
                class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                v-else
            >
                <i class="ri-inbox-line text-100px text-gray-200"></i>
                <span class="text-sm font-medium text-gray-400"
                    >No Project Available</span
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
        v-if="user.position == 'marketing' || user.position == 'admin'"
        class="fixed bottom-12 right-2 w-12 h-12 bg-red-500 rounded-full shadow flex items-center justify-center text-white text-2xl font-bold"
        @click="goto('/projects/add')"
    >
        <i class="ri-add-line"></i>
    </div>
    <BottomSheetAction
        :show="state.selected != null"
        @hide="state.selected = null"
        @action="(e) => (e == 'delete' ? (confirmDelete = true) : null)"
        :actions="
            user.position == 'admin' || user.position == 'marketing'
                ? state.selected?.is_po_deposit
                    ? [
                          {
                              title: 'Edit',
                              link:
                                  '/projects/edit/' +
                                  state.selected?.id +
                                  '?back=/projects',
                              icon: 'ri-pencil-line',
                          },
                          {
                              title: 'Threads',
                              link:
                                  '/threads/' +
                                  state.selected?.id +
                                  '?back=/projects',
                              icon: 'ri-chat-history-line',
                          },
                      ]
                    : [
                          {
                              title: 'Edit',
                              link:
                                  '/projects/edit/' +
                                  state.selected?.id +
                                  '?back=/projects',
                              icon: 'ri-pencil-line',
                          },
                          {
                              title: 'Threads',
                              link:
                                  '/threads/' +
                                  state.selected?.id +
                                  '?back=/projects',
                              icon: 'ri-chat-history-line',
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
                          title: 'Threads',
                          link: '/threads/' + state.selected?.id,
                          icon: 'ri-chat-history-line',
                      },
                  ]
        "
    />
    <BottomsheetProjectFilter
        :show="state.filter_open"
        :status="state.status"
        :po="state.po"
        :po_action="state.po_action"
        :invoice="state.invoice"
        :deposit="state.deposit"
        :start_date="state.start_date"
        :end_date="state.end_date"
        :limit="state.limit"
        @hide="state.filter_open = false"
        @apply="handleFilter"
        @clear="handleClearFilter"
    ></BottomsheetProjectFilter>

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected project?"
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
