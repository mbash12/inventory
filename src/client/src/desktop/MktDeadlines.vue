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
getProjectTodo,
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
    project_types: [
        { value: "gimmick", label: "Gimmick" },
        { value: "design", label: "Design" },
        { value: "printing", label: "Printing" },
        { value: "payment", label: "Supplier Payment" },
    ],
    project_type: []
});
const search = () => {
    state.selected = [];
    loading();
    let filter = {
        filter_date: dayjs(state.filter_date).format("YYYY-MM-DD"),
        page: state.page,
        limit: state.limit,
        noinvoice: true,
        project_type: state.project_type
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "" || filter[key].length === 0) {
            delete filter[key];
        }
    });
    localStorage.setItem("ddl", JSON.stringify(filter));
    getProjectTodo(filter).then((r) => {
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
            r.data?.do.forEach((e) => {
                todo.push({
                    description: `DO Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "blue",
                });
            });
            r.data?.design.forEach((e) => {
                todo.push({
                    description: `Design Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "orange",
                });
            });
            r.data?.bast.forEach((e) => {
                todo.push({
                    description: `BAST Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "teal",
                });
            });
            r.data?.gr.forEach((e) => {
                todo.push({
                    description: `GR/TPB Deadline`,
                    isComplete: false,
                    dates: e.deadline_date,
                    color: "cyan",
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
        <div
            class="flex items-center gap-4 h-40px rounded-lg border w-240px overflow-hidden bg-white px-2 mb-4"
        >
            <i class="ri-box-3-line text-xl text-red-500"></i>
            <select class="w-full h-full" v-model="state.project_type[0]" @change="search">
                <option disabled>Select Project Type</option>
                <option
                    v-for="(e, i) in state.project_types"
                    :key="i"
                    :value="e.value"
                >
                    {{ e.label }}
                </option>
            </select>
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
                                        <span class="font-bold leading-4"
                                            >PO</span
                                        >
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
                                <div class="flex items-center justify-start p-1">
                                    <div class="flex items-start justify-center p-1 flex-col whitespace-nowrap  capitalize">
                                        <strong :style="`color: ${row.deposit_status === 'non-actual' ? '#A045C9' : row.deposit_status === 'actual' ? '#1973C7' : '#19A470'}`">
                                            {{ row.deposit_status }}
                                        </strong>
                                    </div>
                                </div>
                            </td>

                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.po_status === 'available' ? 'bg-[#6FD669]' : row.po_status === 'not available' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.po_status }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.po_status !== 'available'">
                                    {{ row.meta?.po_deadline?.notes }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.do_status === 'uploaded' ? 'bg-[#6FD669]' : row.do_status === 'not uploaded' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.do_status }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.do_status !== 'uploaded'">
                                    {{ row.meta?.do_deadline?.notes }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.design_approved === 'approved' ? 'bg-[#6FD669]' : row.design_approved === 'not approved' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.design_approved }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.design_approved !== 'approved'">
                                    {{ row.meta?.design_deadline?.notes }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.design_files === 'uploaded' ? 'bg-[#6FD669]' : row.design_files === 'not uploaded' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.design_files }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.design_files !== 'uploaded'">
                                    {{ row.meta?.design_deadline?.notes }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.bast_status === 'uploaded' ? 'bg-[#6FD669]' : row.bast_status === 'not uploaded' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.bast_status }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.bast_status !== 'uploaded'">
                                    {{ row.meta?.bast_deadline?.notes }}
                                </div>
                            </td>
                            <td class="p-1">
                                <div class="flex gap-2 items-center justify-start p-1">
                                    <span
                                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex  capitalize"
                                        :class="`${row.gr_status === 'uploaded' ? 'bg-[#6FD669]' : row.gr_status === 'not uploaded' ? 'bg-red-500' : 'bg-gray-200'}`"
                                        >
                                        {{ row.gr_status }}
                                    </span>
                                </div>

                                <div class="mt-1 text-gray-500 text-sm pl-1" v-if="row.gr_status !== 'uploaded'">
                                    {{ row.meta?.gr_deadline?.notes }}
                                </div>
                            </td>
                                <td class="p-1 w-60px">
                                    <div
                                        class="flex items-center justify-start p-1"
                                    >
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
                                                '?back=/desktop/calendar'
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
