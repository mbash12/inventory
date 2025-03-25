div
<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    createThreads,
    getThreads,
    nom,
    goto,
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
import Upload from "../components/Upload.vue";

const user = currentUser.user?.user;
const confirmDelete = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    id: null,
    back: null,
    data: null,
    progress: [],
    threads: [],
    errors: {},
    thread_type: null,
    project_id: null,
    invoice_date: null,
    invoice_number: null,
    client_po_number: null,
    client_po_date: null,
    delivery_deadline: null,
    production_deadline: null,
    po_deadline: null,
    deadline_meta: null,
    invoices: [],
    invoice_status: null,
    invoice_pic: null,
    invoiced_amount: null,
    note: null,

    current_tab: "",
    form_open: false,
    showUpdateButton: false,
    is_plan: true,
    inv_tab: 0,
    mkt_tab:0,
    back:null
});
const formatMetaData = (key, value) => {
    if (key.includes('amount') || key.includes('price')) {
      return nom(value);
    } else if (key.includes('deadline') || key.includes('date')) {
      return dayjs(value).format('DD-MM-YYYY');
    } else {
      return value;
    }
  }
const notiffilter = [
    { title: "All", value: "" },
    { title: "PO Action", value: "po" },
    { title: "Invoice Progress", value: "invoice" },
    { title: "Logistics Update", value: "logistic" },
];
const setnotiffilter = (name) => {
    // loading()
    state.current_tab = name;
};
const resetForm = () => {
    state.invoice_date = null;
    state.invoice_number = null;
    state.client_po_number = null;
    state.client_po_date = null;
    state.delivery_deadline = null;
    state.production_deadline = null;
    state.po_deadline = null;

    state.note = null;
    state.invoice_status = null;
    state.invoice_pic = null;
};

watch(
    () => [state.inv_tab, state.is_plan],
    () => {
        resetForm();
    }
);
watch(
    () => state.form_open,
    () => {
        state.is_plan = true;
        state.inv_tab = 0;
    }
);

const submit = () => {
    loading();
    let data = {
        thread_type: state.thread_type,
        project: state.project_id,
        invoice_date: state.invoice_date,
        invoice_number: state.invoice_number,
        invoice_pic: state.invoice_pic,
        client_po_number: state.client_po_number,
        client_po_date: state.client_po_date,
        invoice_status: state.invoice_status,
        invoiced_amount: state.invoiced_amount,
        delivery_deadline: state.delivery_deadline,
        production_deadline: state.production_deadline,
        po_deadline: state.po_deadline,
    
        note: state.note,
        delete_invoice: state.inv_tab == 2 ? true : false,
    };

    Object.keys(data).forEach((key) => {
        if (!data[key]) {
            delete data[key];
        }
    });
    createThreads(state.project_id, data).then((r) => {
        loading(false);
        if (r.code === 200) {
            alertShowSuccess.value = true;
        } else {
            state.errors = r.errors;
            alertShowFailed.value = true;
        }
    });
};
const thread_type = (position) => {
    switch (position) {
        case "finance":
            return "invoice";
        case "marketing":
            return "po";
        case "delivery":
            return "logistic";
    }
};
const loadData = () => {
    getProject(state.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            let data = r.data;
            state.data = data;
            state.project_id = data?.id;
            state.invoice_date = data?.invoice_date;
            state.invoice_number = data?.invoice_number;
            state.status = data?.invoice_status;
            state.invoices = data?.invoices ? JSON.parse(data?.invoices) : null;
            state.deadline_meta = data?.deadline_meta
                ? JSON.parse(data?.deadline_meta)
                : null;
            state.thread_type = thread_type(user.position);
            if (user.position == "marketing" && data?.client_po_number == null)
                state.showUpdateButton = true;
            if (
                user.position === "delivery" &&
                data?.is_real == 1 &&
                ((data?.is_po_deposit && data?.sent_to_del_at !== null) ||
                    (!data?.is_po_deposit && data?.sent_to_del_at === null))
            ) {
                state.showUpdateButton = true;
            }

            if (
                user.position == "finance" &&
                ((data?.is_real == 0 && data?.is_po_deposit) ||
                    (data?.is_real == 1 && !data?.is_po_deposit))
            )
                state.showUpdateButton = true;

            state.invoiced_amount = state.data?.remaining_amount ?? state.data?.total_price
        }
    });
    getThreads(state.id).then((rr) => {
        if (rr.code === 200) {
            state.progress = rr.data;
        }
    });
};
onMounted(() => {
    loading();
    state.id = route.params.id;
    state.back = route.query?.back;
    loadData();
});
</script>

<template>
    <div class="p-8">
        <div class="flex justify-between">
            <div
                class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center"
            >
                <div class="cursor-pointer mr-4" @click="goto(state.back+'?filter=true')">
                    <i class="ri-arrow-left-line"></i>
                </div>

                <span> Threads </span>
            </div>

            <div class="relative">
                <button
                    class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    @click="() => (state.form_open = true)"
                    >
                    <!-- v-if="state.showUpdateButton" -->
                    <strong>Update</strong>
                    <i class="ri-send-plane-line text-xl"></i>
                </button>
                <div
                    class="fixed top-0 left-0 w-full h-full bg-[#00000011] z-1"
                    @click="() => (state.form_open = false)"
                    :class="state.form_open ? 'block' : 'hidden'"
                ></div>
                <div
                    class="absolute z-1 mt-2 transition-all transform origin-top-right right-0"
                    :class="
                        state.form_open
                            ? 'max-h-400px opacity-100 scale-100'
                            : 'max-h-0 opacity-30 scale-10 overflow-hidden'
                    "
                >
                    <div
                        class="w-500px bg-white rounded-lg shadow-2xl border p-6"
                    >
                        <strong class="text-lg">Update</strong>
                        <form
                            class="mt-4"
                            autocomplete="off"
                            @submit.prevent="submit"
                        >
                            <div
                                class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                                v-if="user.position == 'marketing'"
                            >
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.mkt_tab == 0
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.mkt_tab = 0)"
                                >
                                    Set Deadline
                                </div>
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.mkt_tab == 1
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.mkt_tab = 1)"
                                >
                                    Set PO Number
                                </div>
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.mkt_tab == 2
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.mkt_tab = 2)"
                                >
                                    Cancel Project
                                </div>
                            </div>
                            <div
                                class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                                v-if="user.position == 'delivery'"
                            >
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.is_plan
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.is_plan = true)"
                                >
                                    Set Production Deadline
                                </div>
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        !state.is_plan
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.is_plan = false)"
                                >
                                    Set Delivery Deadline
                                </div>
                            </div>
                            <div
                                class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                                v-if="user.position == 'finance'"
                            >
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.inv_tab == 0
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.inv_tab = 0)"
                                >
                                    Update Progress
                                </div>
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.inv_tab == 1
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.inv_tab = 1)"
                                >
                                    Set Invoice
                                </div>
                                <div
                                    class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                                    :class="
                                        state.inv_tab == 2
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    "
                                    @click="() => (state.inv_tab = 2)"
                                >
                                    Delete Invoice
                                </div>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="user.position == 'delivery'"
                            >
                                <label
                                    class="flex flex-col gap-2 flex-1"
                                    v-if="state.is_plan"
                                >
                                    <span class="text-sm text-left"
                                        >Production Deadline</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="date"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            onfocus="this.showPicker()"
                                            v-model="state.production_deadline"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i
                                                class="ri-calendar-event-line"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'production_deadline'
                                                )
                                            "
                                            >{{
                                                state.errors
                                                    ?.production_deadline[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                                <label
                                    class="flex flex-col gap-2 flex-1"
                                    v-if="!state.is_plan"
                                >
                                    <span class="text-sm text-left"
                                        >Delivery Deadline</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="date"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            onfocus="this.showPicker()"
                                            v-model="state.delivery_deadline"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i
                                                class="ri-calendar-event-line"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'delivery_deadline'
                                                )
                                            "
                                            >{{
                                                state.errors
                                                    ?.delivery_deadline[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>

                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'marketing' &&
                                    state.mkt_tab == 1
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >PO Number</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="text"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.client_po_number"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'client_po_number'
                                                )
                                            "
                                            >{{
                                                state.errors
                                                    ?.client_po_number[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >PO Date</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="date"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            onfocus="this.showPicker()"
                                            v-model="state.client_po_date"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i
                                                class="ri-calendar-event-line"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'client_po_date'
                                                )
                                            "
                                            >{{
                                                state.errors?.client_po_date[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'marketing' &&
                                    state.mkt_tab == 0
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >PO Deadline</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="date"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            onfocus="this.showPicker()"
                                            v-model="state.po_deadline"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i
                                                class="ri-calendar-event-line"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'po_deadline'
                                                )
                                            "
                                            >{{
                                                state.errors?.po_deadline[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'marketing' &&
                                    state.mkt_tab == 2
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >Upload Files</span
                                    >
                                    <Upload :files="[]" @update="console.log" />
                                    
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'po_deadline'
                                                )
                                            "
                                            >{{
                                                state.errors?.po_deadline[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'finance' &&
                                    state.inv_tab == 2
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >Invoice Number</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <select
                                            required
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.invoice_number"
                                        >
                                            <option value="">
                                                Select Invoice number
                                            </option>
                                            <option
                                                v-for="inv in state.invoices"
                                                :key="inv.invoice_number"
                                                :value="inv.invoice_number"
                                            >
                                                {{ inv.invoice_number }}
                                            </option>
                                        </select>
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoice_number'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoice_number[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'finance' &&
                                    state.inv_tab == 1
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >Invoice Number</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="text"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.invoice_number"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoice_number'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoice_number[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >Invoice Date</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="date"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            onfocus="this.showPicker()"
                                            v-model="state.invoice_date"
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i
                                                class="ri-calendar-event-line"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoice_date'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoice_date[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="
                                    user.position == 'finance' &&
                                    state.inv_tab == 1
                                "
                            >
                                <label class="flex flex-col gap-2 flex-1">
                                    <span class="text-sm text-left"
                                        >Amount
                                        <small
                                            >(Max :
                                            {{
                                                nom(
                                                    state.data
                                                        ?.remaining_amount ??
                                                        state.data?.total_price
                                                )
                                            }})</small
                                        ></span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                                    >
                                        <input
                                            type="number"
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.invoiced_amount"
                                            :max="
                                                state.data?.remaining_amount ??
                                                state.data?.total_price
                                            "
                                            required
                                        />
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoiced_amount'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoiced_amount[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>
                            <div
                                class="flex gap-4 w-full"
                                v-if="user.position == 'finance'"
                            >
                                <label
                                    class="flex flex-col gap-2 flex-1"
                                    v-if="state.inv_tab == 0"
                                >
                                    <span class="text-sm text-left">PIC</span>
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center pr-2"
                                    >
                                        <select
                                            required
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.invoice_pic"
                                        >
                                            <option value="">Select PIC</option>
                                            <option value="marketing">
                                                Marketing
                                            </option>
                                            <option value="finance">
                                                Finance
                                            </option>
                                            <option value="delivery">
                                                Delivery
                                            </option>
                                        </select>
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-file-user-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoice_pic'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoice_pic[0]
                                            }}</span
                                        >
                                    </div>
                                </label>

                                <label
                                    class="flex flex-col gap-2 flex-1"
                                    v-if="state.inv_tab != 2"
                                >
                                    <span class="text-sm text-left"
                                        >Status</span
                                    >
                                    <div
                                        class="w-full border rounded-lg bg-white h-45px relative flex items-center pr-2"
                                    >
                                        <select
                                            required
                                            class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                            v-model="state.invoice_status"
                                        >
                                            <option value="" selected>
                                                Select Status
                                            </option>
                                            <option value="progress">
                                                Progress
                                            </option>
                                            <option value="sent">Sent</option>
                                        </select>
                                        <div
                                            class="absolute left-4 text-red-500 text-xl"
                                        >
                                            <i class="ri-article-line"></i>
                                        </div>
                                    </div>
                                    <div class="h-3 flex -mt-1">
                                        <span
                                            class="text-xs text-red-500"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    'invoice_status'
                                                )
                                            "
                                            >{{
                                                state.errors?.invoice_status[0]
                                            }}</span
                                        >
                                    </div>
                                </label>
                            </div>

                            <label class="flex flex-col gap-2">
                                <span class="text-sm text-left">Note</span>
                                <div
                                    class="w-full border rounded-lg bg-white relative flex items-center"
                                >
                                    <textarea
                                        required
                                        class="bg-transparent w-full h-100px rounded-lg p-4 noicon text-14px"
                                        v-model="state.note"
                                    ></textarea>
                                </div>
                                <div class="h-3 flex -mt-1">
                                    <span
                                        class="text-xs text-red-500"
                                        v-if="
                                            state.errors.hasOwnProperty('note')
                                        "
                                        >{{ state.errors?.note[0] }}</span
                                    >
                                </div>
                            </label>
                            <button
                                class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center ml-auto"
                            >
                                <i class="ri-save-line text-xl"></i>
                                <strong>Save</strong>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-8">
            <div class="w-1/2 w-full bg-white rounded-lg shadow p-6 proform">
                <div class="px-3">
                    <strong class="block mb-4 text-lg text-left"
                        >Project Details</strong
                    >
                </div>

                <div class="flex justify-between px-3 pt-2 items-center">
                    <div class="flex gap-2 items-center h-6">
                        <span
                            class="text-sm text-blue-gray-500 font-semibold"
                            v-show="state.data?.client_po_number !== null"
                            >#PO {{ state.data?.client_po_number }}</span
                        >
                        <span
                            class="text-sm text-blue-gray-500 font-semibold"
                            v-show="state.data?.client_po_number === null"
                            >#PO Dalam Proses</span
                        >
                        <i
                            v-show="state.data?.client_po_number === null"
                            class="ri-error-warning-fill text-xl text-app-500"
                        ></i>
                    </div>
                    <div class="flex gap-2 items-center h-7">
                        <span class="text-sm text-black"
                            >#JOB {{ state.data?.job_number }}</span
                        >
                    </div>
                </div>
                <div class="flex justify-between gap-1 px-3 items-start">
                    <div class="flex w-full flex-col">
                        <div class="flex items-center gap-x-2 flex-wrap">
                            <span class="font-semibold text-black">{{
                                state.data?.products_data[0]?.name
                            }}</span>
                            <span
                                class="text-blue-gray-500 text-xs"
                                v-if="state.data?.products_data.length > 1"
                                >+{{
                                    state.data?.products_data.length - 1
                                }}
                                other(s)</span
                            >
                        </div>
                        <span class="text-app-900 text-sm text-left"
                            >by {{ state.data?.client_company }}</span
                        >
                    </div>
                    <div
                        class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap flex"
                        :class="
                            state.data?.status === 'delivered'
                                ? 'bg-[#6FD669]'
                                : state.data?.status === 'partial'
                                ? 'bg-[#4DC8E3]'
                                : state.data?.status === 'ready'
                                ? 'bg-[#E5C100]'
                                : state.data?.status === 'cancel'
                                ? 'bg-[#E44545]'
                                : state.data?.status === 'production'
                                ? 'bg-[#3f51b5]'
                                : state.data?.status === 'new'
                                ? 'bg-[#AD58D4]'
                                : ''
                        "
                    >
                        {{
                            state.data?.status === "delivered"
                                ? "Delivered"
                                : state.data?.status === "partial"
                                ? "Partial Delivery"
                                : state.data?.status === "ready"
                                ? "Ready to deliver"
                                : state.data?.status === "cancel"
                                ? "Cancel"
                                : state.data?.status === "production"
                                ? "On Production"
                                : state.data?.status === "new"
                                ? "New"
                                : ""
                        }}
                    </div>
                </div>
                <div class="border-b w-full mt-2"></div>
                <div class="px-3 py-2 flex">
                    <div class="flex w-full">
                        <!-- <div class="flex-1 flex flex-col items-start">
                                    <span class="text-xs text-blue-gray-500"
                                        >Produced</span
                                    >
                                    <div class="text-black">
                                        <strong>{{ state.data?.total }}</strong
                                        >/<span>{{ state.data?.total }}</span>
                                    </div>
                                </div> -->
                        <div class="flex-1 flex flex-col items-start">
                            <span class="text-xs text-blue-gray-500"
                                >Stored</span
                            >
                            <div class="text-black">
                                <strong>{{
                                    nom(state.data?.stored ?? 0)
                                }}</strong
                                >/<span>{{ nom(state.data?.total) }}</span>
                            </div>
                        </div>
                        <div class="flex-1 flex flex-col items-center">
                            <span class="text-xs text-blue-gray-500"
                                >Delivered</span
                            >
                            <div class="text-black">
                                <strong>{{
                                    nom(state.data?.delivered ?? 0)
                                }}</strong
                                >/<span>{{ nom(state.data?.total) }}</span>
                            </div>
                        </div>
                        <div class="flex-1 flex flex-col items-end">
                            <span class="text-xs text-blue-gray-500"
                                >Invoice Status</span
                            >
                            <div
                                class="text-xs font-medium capitalize"
                                :class="
                                    state.data?.invoice_status == 'progress'
                                        ? 'text-orange-500'
                                        : 'text-green-500'
                                "
                            >
                                <span>{{ state.data?.invoice_status }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-b w-full mb-2"></div>
                <div class="px-3 pb-2">
                    <div class="flex py-2">
                        <div class="flex flex-col items-start flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Pelangi's PIC</span
                            >
                            <strong class="mt-1">{{
                                state.data?.pic_name
                            }}</strong>
                        </div>
                        <div class="flex flex-col items-end flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Client's PIC</span
                            >
                            <strong class="mt-1">{{
                                state.data?.client_pic_name
                            }}</strong>
                        </div>
                    </div>
                    <div class="flex py-2 justify-between">
                        <div class="flex flex-col items-start flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Tanggal PO</span
                            >
                            <strong class="mt-1">{{
                                state.data?.client_po_date
                                    ? dayjs(state.data?.client_po_date).format(
                                          "D MMMM YYYY"
                                      )
                                    : "Dalam Proses"
                            }}</strong>
                        </div>

                        <div class="flex flex-col items-end flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Price</span
                            >
                            <strong class="mt-1 text-sm">{{
                                state.data?.total_price &&
                                user.position != "delivery"
                                    ? nom(state.data?.total_price ?? 0)
                                    : 0
                            }}</strong>
                        </div>
                    </div>
                    <div class="flex py-2">
                        <div class="flex flex-col items-start flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Invoices</span
                            >
                            <strong v-if="!state.invoices">-</strong>
                            <div
                                class="mt-1 flex justify-between w-full items-center"
                                v-for="invoice in state.invoices"
                            >
                                <span class="text-sm">
                                    {{ invoice.invoice_number }}
                                </span>

                                <span class="text-xs">
                                    {{ dayjs(invoice.invoice_date).format("DD-MM-YYYY") }}
                                </span>
                                
                                <span class="text-xs">
                                    {{ nom(invoice?.invoice_amount ?? 0) }}
                                </span>
                            </div>
                        </div>
                        <!-- <div class="flex flex-col items-end flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Invoiced Amount</span
                            >
                            <strong
                                class="mt-1"
                                v-if="user.position !== 'delivery'"
                                >{{
                                    state.data?.invoiced_amount
                                        ? nom(state.data?.invoiced_amount)
                                        : "-"
                                }}</strong
                            >
                        </div> -->
                    </div>
                </div>
                <strong
                    class="text-sm text-blue-gray-500 my-4 flex bg-gray-100 px-3 py-2"
                    >Deadlines
                </strong>
                <div class="px-3 pb-2">
                    <div class="flex py-2 justify-between">
                        <div class="flex flex-col items-start flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-blue-gray-500"
                                    >Production Finish Deadline
                                </span>
                                <i
                                    v-show="
                                        dayjs().isAfter(
                                            state.deadline_meta
                                                ?.production_deadline?.reminder
                                        ) && state.data?.status != 'delivered'
                                    "
                                    class="ri-error-warning-fill text-xl text-app-500"
                                ></i>
                            </div>
                            <strong class="mt-1">{{
                                state.data?.production_deadline
                                    ? dayjs(
                                          state.data?.production_deadline
                                      ).format("D MMMM YYYY")
                                    : "-"
                            }}</strong>
                        </div>

                        <div class="flex flex-col items-end flex-1">
                            <div class="flex items-center gap-2">
                                <i
                                    v-show="
                                        dayjs().isAfter(
                                            state.deadline_meta
                                                ?.delivery_deadline?.reminder
                                        )
                                        && state.data?.status != 'delivered'
                                    "
                                    class="ri-error-warning-fill text-xl text-app-500"
                                ></i>
                                <span class="text-xs text-blue-gray-500"
                                    >Delivery Start Deadline</span
                                >
                            </div>
                            <strong class="mt-1">{{
                                state.data?.delivery_deadline
                                    ? dayjs(
                                          state.data?.delivery_deadline
                                      ).format("D MMMM YYYY")
                                    : "-"
                            }}</strong>
                        </div>
                    </div>
                </div>
                <div class="px-3 pb-2">
                    <div class="flex py-2 justify-between">
                        <div class="flex flex-col items-start flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-blue-gray-500"
                                    >PO Action Deadline</span
                                >
                                <i
                                    v-show="
                                        dayjs().isAfter(
                                            state.deadline_meta?.po_deadline
                                                ?.reminder
                                        )
                                        && state.data?.client_po_number === null
                                    "
                                    class="ri-error-warning-fill text-xl text-app-500"
                                ></i>
                            </div>
                            <strong class="mt-1">{{
                                state.data?.po_deadline
                                    ? dayjs(state.data?.po_deadline).format(
                                          "D MMMM YYYY"
                                      )
                                    : "-"
                            }}</strong>
                        </div>
                    </div>
                </div>
                <strong
                    class="text-sm text-blue-gray-500 my-4 flex bg-gray-100 px-3 py-2"
                    >Products
                    <span
                        >&nbsp;({{ state.data?.products_data.length }})</span
                    ></strong
                >
                <div class="px-3">
                    <div
                        class="flex flex-col py-3 border-b"
                        v-for="(item, i) in state.data?.products_data"
                        :key="i"
                    >
                        <div class="flex justify-between text-sm">
                            <strong>{{ item.name }}</strong>
                            <span
                                >{{ nom(item.delivered) }} /
                                {{ nom(item.quantity) }}</span
                            >
                        </div>
                        <div class="text-xs mt-1 text-gray-500">
                            {{ item.description }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-1/2 w-full bg-white rounded-lg shadow p-6 proform">
                <div class="px-3">
                    <strong class="block mb-4 text-lg text-left"
                        >Update History</strong
                    >
                </div>
                <div class="px-3 border-b flex gap-3">
                    <template v-for="(item, i) in notiffilter" :key="i">
                        <div
                            class="py-3 border-b-2 px-1 flex gap-1 items-center -mb-1px cursor-pointer"
                            :class="
                                state.current_tab == item.value
                                    ? 'border-[#DF3737]'
                                    : 'border-transparent'
                            "
                            @click="() => setnotiffilter(item.value)"
                        >
                            <span
                                class="text-12px font-inter capitalize"
                                :class="
                                    state.current_tab == item.value
                                        ? 'text-[#DF3737] font-bold'
                                        : 'text-[#667085]'
                                "
                                >{{ item.title }}</span
                            >
                            <span
                                class="text-10px w-18px h-18px rounded-full flex items-center justify-center -mt-2px font-inter"
                                :class="
                                    state.current_tab == item.value
                                        ? 'bg-[#FFD0D0] text-[#DF3737] font-bold'
                                        : 'bg-[#DFE1E7] text-[#667085]'
                                "
                                >{{
                                    item.value == ""
                                        ? state.progress.length
                                        : state.progress.filter(
                                              (x) => x.type == item.value
                                          ).length
                                }}</span
                            >
                        </div>
                    </template>
                </div>
                <div class="flex flex-col">
                    <template
                        v-for="(item, i) in state.progress.filter((x) =>
                            state.current_tab == ''
                                ? x
                                : x.type == state.current_tab
                        )"
                        :key="i"
                    >
                        <div class="px-3 py-3 border-b flex gap-3">
                            <div class="flex items-start">
                                <div
                                    class="w-40px h-40px bg-[#FFCFCFB2] rounded-full flex items-center justify-center text-[#DF3737]"
                                >
                                    <i class="ri-send-plane-line text-xl"></i>
                                </div>
                            </div>
                            <div class="flex flex-col flex-1">
                                <span
                                    class="text-xs font-medium text-[#DF3737]"
                                    >{{
                                        item.type == "po"
                                            ? "PO Action"
                                            : item.type == "invoice"
                                            ? "Invoice Progress"
                                            : "Logistic Update"
                                    }}</span
                                >
                                <div
                                    class="text-sm mt-1"
                                    v-html="
                                        item.notes?.replace(
                                            /(?:\r\n|\r|\n)/g,
                                            '<br>'
                                        )
                                    "
                                ></div>
                                <div
                                    class="text-sm mt-1 flex flex-col bg-gray-50 p-2 rounded"
                                >
                                    <span
                                        v-for="item in Object.entries(
                                            JSON.parse(item?.meta_data ?? '{}')
                                        )"
                                        class="text-xs text-gray-500"
                                        v-if="
                                            !(
                                                [
                                                    'invoiced_amount',
                                                    'total_price',
                                                    'remaining_amount',
                                                ].includes(item[0]) &&
                                                user.position === 'delivery'
                                            )
                                        "
                                        ><span class="capitalize">{{
                                            item[0].replace("_", " ")
                                        }}</span>
                                        : <span>{{ formatMetaData(item[0], item[1]) }}</span>
                                        </span
                                    >
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-xs text-gray-500">{{
                                    dayjs(item.created_at).format("D MMM YYYY")
                                }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <Alert
        type="failed"
        title="Failed to save"
        content=""
        :show="alertShowFailed"
        @hide="
            () => {
                alertShowFailed = false;
            }
        "
    ></Alert>
    <Alert
        type="success"
        title="Project Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                loadData();
                resetForm();
                state.form_open = false;
            }
        "
    ></Alert>
</template>
