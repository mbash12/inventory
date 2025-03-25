<script setup>
import { ref, computed, reactive, onMounted, watch } from "vue";
import dayjs from "dayjs";
import Popover from "../components/Popover.vue";
import {
    getProjectList,
    deleteProject,
    nom,
    sortByProperty,
    currentUser,
    getThreadsCount,
} from "../services/service";

import { loading } from "../services/router";
import { useRoute, useRouter } from "vue-router";
const todos = ref([]);
const route = useRoute();
const attributes = computed(() => [
    ...todos.value.map((todo) => ({
        dates: todo.dates,
        dot: {
            color: todo.color,
            ...(todo.isComplete && { class: "opacity-75" }),
        },
        popover: {
            label: todo.description,
        },
    })),
]);

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
const state = reactive({
    data: [],
    meta: {},
    filter_date: null,
    limit: 10,
    page: 1,
});
const search = () => {
    state.selected = [];
    loading();
    let filter = {
        filter_date: dayjs(state.filter_date).format("YYYY-MM-DD"),
        page: state.page,
        limit: state.limit,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    localStorage.setItem("ddl", JSON.stringify(filter));
    getProjectList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data.map((e) => {
                e.invoice = e.po_deposit_data?.invoices
                    ? JSON.parse(e.po_deposit_data?.invoices).reverse()[0]
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

const updateTodo = () => {
    getThreadsCount().then((r) => {
        if (r.code === 200) {
            let todo = [];
            r.data?.delivery.forEach((e) => {
                todo.push({
                    description: `Delivery Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "red",
                });
            });
            r.data?.po.forEach((e) => {
                todo.push({
                    description: `PO Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "green",
                });
            });
            r.data?.production.forEach((e) => {
                todo.push({
                    description: `Production Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "purple",
                });
            });
            todos.value = todo;
        }
    });
};

watch(
    () => state.filter_date,
    () => {
        state.page = 1;
        search();
    }
);

onMounted(() => {
    if (route.query?.filter) {
        let flt = localStorage.getItem("ddl");
        if (flt) {
            flt = JSON.parse(flt);
            state.filter_date = flt?.filter_date ?? new Date();
        }
    } else {
        localStorage.removeItem("ddl");
        state.filter_date = new Date();
    }
    updateTodo();
});
</script>
<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Deadlines
        </div>
        <div class="flex gap-6">
            <VDatePicker
                :attributes="attributes"
                mode="date"
                v-model="state.filter_date"
                class="shadow !border-none"
            />
            <div
                class="w-full bg-white rounded-lg shadow overflow-hidden flex-1"
            >
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
                                            >PO</span
                                        >
                                    </div>
                                </th>
                                <!-- <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >PO</span
                                        >
                                    </div>
                                </th>
                                <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4 group"
                                            >Client</span
                                        >
                                    </div>
                                </th> -->
                                <!-- <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                                    >
                                        <span class="font-bold leading-4"
                                            >Invoice</span
                                        >
                                    </div>
                                </th> -->
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
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >Status</span
                                        >
                                    </div>
                                </th>
                                <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >Production Remark</span
                                        >
                                    </div>
                                </th>
                                <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >Delivery Remark</span
                                        >
                                    </div>
                                </th>
                                <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >PO Remark</span
                                        >
                                    </div>
                                </th>
                                <th class="p-1">
                                    <div
                                        class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full group"
                                    >
                                        <span class="font-bold leading-4"
                                            >Invoice Remark</span
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
                                            <span class="text-xs text-gray-400">
                                                Job Number
                                            </span>
                                            {{ row.job_number }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        <div
                                            class="flex items-start justify-center p-1 flex-col"
                                        >
                                            <span class="text-gray-400">
                                                PO Number
                                            </span>
                                            <span
                                                v-if="!row.client_po_number"
                                                class="flex items-center gap-2"
                                            >
                                                On Process
                                            </span>
                                            <span
                                                v-if="row.client_po_number"
                                            >
                                                {{ row.client_po_number }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center justify-start p-1"
                                    >
                                        <div
                                            class="flex items-start justify-center p-1 flex-col"
                                        >
                                        
                                        <span class="text-xs text-gray-400">
                                                Client
                                            </span>
                                            <div
                                                class="w-180px truncate"
                                                :title="row.client_company"
                                            >
                                                {{ row.client_company }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- <td class="p-1">
                                    <div
                                        class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                    >
                                        {{ row.client_po_number ?? "" }}
                                        <span
                                            v-if="!row.client_po_number"
                                            class="flex items-center gap-2"
                                        >
                                            On Process
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
                                                          ).format(
                                                              "DD MMM YYYY"
                                                          )
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
                                </td> -->

                                <!-- <td class="p-1">
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
                                            <Popover
                                                v-if="
                                                    row?.meta?.invoice_deadline
                                                "
                                                :content="
                                                    row.meta?.invoice_deadline?.notes.replace(
                                                        /\r\n/g,
                                                        '<br />'
                                                    )
                                                "
                                                :title="'Finance Note'"
                                            >
                                                <i
                                                    class="ri-information-fill text-xl text-blue-500"
                                                ></i>
                                            </Popover>
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
                                                    row.invoice
                                                        ?.invoice_number ?? ""
                                                }}
                                            </div>
                                            <div class="flex gap-1">
                                                <span
                                                    class="text-xs text-gray-400"
                                                >
                                                    <span
                                                        class="capitalize"
                                                        :class="
                                                            row.invoice_status ==
                                                            'progress'
                                                                ? 'text-orange-500'
                                                                : 'text-green-500'
                                                        "
                                                        >{{
                                                            row.po_deposit_data
                                                                ?.invoice_status
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
                                    </div>
                                </td> -->
                                <td class="p-1">
                                    <div
                                        class="flex items-center justify-start p-1"
                                    >
                                        <div
                                            class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                            v-if="
                                                row.is_po_deposit &&
                                                !row.is_real
                                            "
                                            :title="`Deposit Non Actual`"
                                        >
                                            <span
                                                class="text-2xl text-green-600"
                                            >
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
                                            <span
                                                class="text-2xl text-green-600"
                                            >
                                                <i class="ri-circle-fill"></i>
                                            </span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex items-start justify-center p-1 flex-col whitespace-nowrap"
                                            title="Project"
                                        >
                                            <span
                                                class="text-2xl text-blue-600"
                                            >
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
                                            :class="
                                                row.status === 'delivered'
                                                    ? 'bg-[#6FD669]'
                                                    : row.status === 'partial'
                                                    ? 'bg-[#4DC8E3]'
                                                    : row.status === 'ready'
                                                    ? 'bg-[#E5C100]'
                                                    : row.status === 'cancel'
                                                    ? 'bg-[#E44545]'
                                                    : row?.status ===
                                                      'production'
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
                                                    : row.status ===
                                                      "production"
                                                    ? "On Production"
                                                    : row.status === "new"
                                                    ? "New"
                                                    : ""
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="p-1">
                                    <div
                                        class="flex items-center justify-start p-1"
                                        v-html="
                                            row?.meta?.production_deadline?.notes?.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                    ></div>
                                </td>
                                <td class="p-1">
                                    <div
                                        class="flex items-center justify-start p-1"
                                        v-html="
                                            row?.meta?.delivery_deadline?.notes?.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                    ></div>
                                </td>
                                <td class="p-1">
                                    <div
                                        class="flex items-center justify-start p-1"
                                        v-html="
                                            row?.meta?.po_deadline?.notes?.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                    ></div>
                                </td>
                                <td class="p-1">
                                    <div
                                        class="flex items-center justify-start p-1"
                                        v-html="
                                            row?.meta?.invoice_deadline?.notes?.replace(
                                                /\r\n/g,
                                                '<br />'
                                            )
                                        "
                                    ></div>
                                </td>
                                <td class="p-1 w-60px">
                                    <div
                                        class="flex items-center justify-start p-1"
                                    >
                                        <router-link
                                            :to="
                                                '/desktop/threads/' +
                                                row.id +
                                                '?back=/desktop/deadlines'
                                            "
                                        >
                                            <div
                                                class="text-xl px-2 py-1 text-[#667085] hover:bg-gray-100 rounded"
                                                title="Threads"
                                            >
                                                <i
                                                    class="ri-chat-history-line"
                                                ></i>
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
                        Page {{ state.page }} of
                        {{ state.meta?.total_pages || 1 }}
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
    </div>
</template>
