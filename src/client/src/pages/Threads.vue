<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import Alert from "../components/Alert.vue";
import {
    getProject,
    getInventoriess,
    getLogsList,
    getProgress,
    createProgress,
    nom,
    currentUser,
    getThreads,
    createThreads,
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import ThreadForm from "../components/ThreadForm.vue";
const user = currentUser.user?.user;

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
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const router = useRouter();
const route = useRoute();
const currentUrl = route.path;
const showProduct = ref(false);
const showProgress = ref(false);
const showDeadlines = ref(false);
const state = reactive({
    current_tab: "",
    edit_open: false,
    progress: [],
    errors: {},
    project_id: null,
    invoice_date: null,
    invoice_number: null,
    invoices: [],
    pic: null,
    status: null,
    note: null,
    showUpdateButton: false,
});
const followup = () => {
    state.edit_open = true;
};
const handleAction = (data) => {
    submit(data);
};

const submit = (data1) => {
    loading();
    let data = {
        project: state.project_id,
        thread_type: state.thread_type,
        invoice_date: data1.invoice_date,
        invoice_number: data1.invoice_number,
        invoice_pic: data1.invoice_pic,
        client_po_number: data1.client_po_number,
        client_po_date: data1.client_po_date,
        invoice_status: data1.invoice_status,
        invoiced_amount: data1.invoiced_amount,
        delivery_deadline: data1.delivery_deadline,
        production_deadline: data1.production_deadline,
        po_deadline: data1.po_deadline,
        alert_time: data1.alert_time,
        note: data1.note,
        delete_invoice: data1.inv_tab == 2 ? true : false,
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
            state.edit_open = false;
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
    state.back = (route.query?.back ?? "/projects") + "?filter=true";
    loadData();
});
</script>
<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Threads" :back="state.back"></Navbar>
        <div class="flex-1 flex flex-col min-h-0">
            <div class="flex-1 overflow-auto min-h-0">
                <div class="min-h-full min-w-full flex flex-col bg-gray-50">
                    <div
                        class="rounded-none bg-white shadow-md shadow-gray-100"
                    >
                        <div
                            class="flex justify-between px-4 pt-2 items-center"
                        >
                            <div class="flex gap-1 items-center h-6">
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="
                                        state.data?.client_po_number !== null
                                    "
                                    >#PO
                                    {{ state.data?.client_po_number }}</span
                                >
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="
                                        state.data?.client_po_number === null
                                    "
                                    >#PO Dalam Proses</span
                                >
                                <i
                                    v-show="
                                        state.data?.client_po_number === null
                                    "
                                    class="ri-error-warning-fill text-lg text-app-500"
                                ></i>
                            </div>
                            <div class="flex gap-2 items-center h-7">
                                <span class="text-xs text-black"
                                    >#JOB {{ state.data?.job_number }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex justify-between gap-1 px-4 items-start"
                        >
                            <div class="flex w-full flex-col">
                                <div
                                    class="flex items-center gap-1 mb-2 flex-wrap"
                                >
                                    <span
                                        class="font-medium text-gray-400 text-xs leading-4 pr-2 w-full"
                                        >{{ state.data?.title }}</span
                                    >
                                    <span
                                        class="font-semibold text-black text-sm pr-2 leading-4"
                                        >{{
                                            state.data?.products_data[0]?.name
                                        }}</span
                                    >
                                    <span
                                        class="text-blue-gray-500 text-xs"
                                        v-if="
                                            state.data?.products_data.length > 1
                                        "
                                        >+{{
                                            state.data?.products_data.length - 1
                                        }}
                                        other(s)</span
                                    >
                                </div>
                                <span class="text-app-900 text-xs text-left"
                                    >by {{ state.data?.client_company }}</span
                                >
                            </div>
                            <div
                                class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
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
                                {{ state.data?.status }}
                            </div>
                        </div>
                        <div class="border-b w-full mt-2"></div>
                        <div class="px-4 py-2 flex">
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
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(state.data?.stored ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.data?.total)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-center">
                                    <span
                                        class="text-xs text-blue-gray-500 flex-1"
                                        >Delivered</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(state.data?.delivered ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.data?.total)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-end">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoice Status</span
                                    >
                                    <div
                                        class="text-xs font-medium capitalize"
                                        :class="
                                            state.data?.invoice_status ==
                                            'progress'
                                                ? 'text-orange-500'
                                                : 'text-green-500'
                                        "
                                    >
                                        <span>{{
                                            state.data?.invoice_status
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-b w-full mb-2"></div>
                        <div class="px-4 pb-2">
                            <div class="flex py-2">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Pelangi's PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.pic_name
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-end flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Client's PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.client_pic_name
                                    }}</strong>
                                </div>
                            </div>
                            <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Tanggal PO</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.client_po_date
                                            ? dayjs(
                                                  state.data?.client_po_date
                                              ).format("D MMMM YYYY")
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
                            <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoices</span
                                    >
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
                                        class="mt-1 text-sm"
                                        v-if="user.position !== 'delivery'"
                                        >{{
                                            state.data?.invoiced_amount
                                                ? nom(
                                                      state.data
                                                          ?.invoiced_amount
                                                  )
                                                : "-"
                                        }}</strong
                                    >
                                </div> -->
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-4 py-3 flex justify-between items-center border-b"
                        @click="showDeadlines = !showDeadlines"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Deadlines
                        </strong>
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-3 pb-2 bg-white" v-show="showDeadlines">
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
                                                    ?.production_deadline
                                                    ?.reminder
                                            )
                                        "
                                        class="ri-error-warning-fill text-xl text-app-500"
                                    ></i>
                                </div>
                                <strong class="mt-1 text-sm">{{
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
                                                    ?.delivery_deadline
                                                    ?.reminder
                                            )
                                        "
                                        class="ri-error-warning-fill text-xl text-app-500"
                                    ></i>
                                    <span class="text-xs text-blue-gray-500"
                                        >Delivery Start Deadline</span
                                    >
                                </div>
                                <strong class="mt-1 text-sm">{{
                                    state.data?.delivery_deadline
                                        ? dayjs(
                                              state.data?.delivery_deadline
                                          ).format("D MMMM YYYY")
                                        : "-"
                                }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="px-3 pb-2 bg-white" v-show="showDeadlines">
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
                                        "
                                        class="ri-error-warning-fill text-xl text-app-500"
                                    ></i>
                                </div>
                                <strong class="mt-1 text-sm">{{
                                    state.data?.po_deadline
                                        ? dayjs(state.data?.po_deadline).format(
                                              "D MMMM YYYY"
                                          )
                                        : "-"
                                }}</strong>
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-4 py-3 flex justify-between items-center border-b"
                        @click="showProduct = !showProduct"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Products
                            <span
                                >({{ state.data?.products_data.length }})</span
                            ></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showProduct">
                        <div
                            class="flex flex-col py-3 border-b"
                            v-for="(item, i) in state.data?.products_data"
                            :key="i"
                        >
                            <div class="flex justify-between text-sm gap-2">
                                <strong class="leading-4">{{
                                    item.name
                                }}</strong>
                                <span class="flex-shrink-0 whitespace-nowrap">
                                    <div
                                        class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
                                        :class="
                                            item?.status == 'delivered'
                                                ? 'bg-[#6FD669]'
                                                : item?.status == 'partial'
                                                ? 'bg-[#4DC8E3]'
                                                : item?.status == 'ready'
                                                ? 'bg-[#E5C100]'
                                                : item?.status == 'cancel'
                                                ? 'bg-[#E44545]'
                                                : item?.status == 'production'
                                                ? 'bg-[#3f51b5]'
                                                : ''
                                        "
                                    >
                                        {{ item?.status }}
                                    </div>
                                </span>
                            </div>
                            <div class="flex justify-between text-sm gap-2">
                                <div class="font-semibold">
                                    <span class="text-xs text-gray-600">
                                        Stored:
                                    </span>
                                    <span
                                        class="text-xs text-gray-600"
                                        v-html="
                                            item?.stored != ''
                                                ? item?.stored
                                                : '0'
                                        "
                                    >
                                    </span>
                                </div>
                                <span class="flex-shrink-0 whitespace-nowrap"
                                    >{{ nom(item.delivered) }} /
                                    {{ nom(item.quantity) }}</span
                                >
                            </div>
                            <div class="text-xs mt-2px text-gray-500">
                                <div v-html="item.description"></div>
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-4 py-3 flex justify-between items-center border-b"
                        @click="showProgress = !showProgress"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Update History
                            <span>({{ state.progress?.length }})</span></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-3 border-b flex gap-3" v-show="showProgress">
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
                                    class="text-10px font-inter capitalize"
                                    :class="
                                        state.current_tab == item.value
                                            ? 'text-[#DF3737] font-bold'
                                            : 'text-[#667085]'
                                    "
                                    >{{ item.title }}</span
                                >
                                <span
                                    class="text-8px w-18px h-18px rounded-full flex items-center justify-center -mt-2px font-inter"
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
                    <div class="flex flex-col bg-white" v-show="showProgress">
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
                                        <i
                                            class="ri-send-plane-line text-xl"
                                        ></i>
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
                                        class="text-xs mt-1"
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
                                        <template
                                            v-for="item in Object.entries(
                                                JSON.parse(
                                                    item?.meta_data ?? '{}'
                                                )
                                            )"
                                        >
                                            <span
                                                v-if="
                                                    !(
                                                        [
                                                            'invoiced_amount',
                                                            'total_price',
                                                            'remaining_amount',
                                                        ].includes(item[0]) &&
                                                        user.position ===
                                                            'delivery'
                                                    )
                                                "
                                                class="text-xs text-gray-500"
                                            >
                                                <span class="capitalize">{{
                                                    item[0].replace("_", " ")
                                                }}</span>
                                                : {{ item[1] }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="text-xs text-gray-500">{{
                                        dayjs(item.created_at).format(
                                            "D MMM YYYY"
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 w-full" v-if="state.showUpdateButton">
            <button
                type="button"
                class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                @click="followup"
            >
                <i class="ri-send-plane-line text-xl"></i>
                <span class="text-sm"> Update </span>
            </button>
        </div>
    </main>

    <ThreadForm
        :show="state.edit_open"
        :user="user"
        :project="state.data"
        @hide="state.edit_open = false"
        @action="handleAction"
    ></ThreadForm>

    <Alert
        type="fail"
        title="Failed to save"
        :content="alertShowFailed ?? ''"
        :show="alertShowFailed != false"
        @hide="alertShowFailed = false"
    ></Alert>
    <Alert
        type="success"
        title="Success to save"
        :content="'Progress saved successfully'"
        :show="alertShowSuccess != false"
        @hide="
            alertShowSuccess = false;
            showProgress = true;
            loadData();
        "
    ></Alert>
</template>
