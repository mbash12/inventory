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
    currentUser
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import InvoiceProgress from "../components/InvoiceProgress.vue";
const user = currentUser.user?.user;

const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const router = useRouter();
const route = useRoute();
const currentUrl = route.path;
const showProduct = ref(false);
const showProgress = ref(false);
const state = reactive({
    edit_open:false,
    progress: [],
    errors: {},
    project_id: null,
    invoice_date: null,
    invoice_number: null,
    invoices:[],
    pic: null,
    status: null,
    note: null,
});
const followup = () => {
    
    state.edit_open = true;
}
const handleAction = (data) => {
    state.edit_open = false;
    if (data !== null) {
        loading();
        let datas = {
            project: state.project_id,
            invoice_date: data.invoice_date,
            invoice_number: data.invoice_number,
            pic: data.pic,
            status: data.status,
            note: data.note,
        };
        createProgress(state.project_id, datas).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                state.errors = r.errors;
                alertShowFailed.value = true;
            }
        });
    }
};
onMounted(() => {
    loading();
    let id = currentUrl.split("/").slice(-1);
    getProject(id).then((r) => {
        loading(false);
        if (r.code === 200) {
            let data = r.data;
            state.data = data;
            state.project_id = data.id;
            state.invoice_date = data.invoice_date
            state.invoice_number = data.invoice_number
            state.status = data.invoice_status
            state.invoices = data.po_deposit_data?.invoices ? JSON.parse(data.po_deposit_data.invoices) : null
        }
    });
    getProgress(id).then((rr) => {
        if (rr.code === 200) {
            state.progress = rr.data;
        }
    });
});
</script>
<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Invoice Progress" back="/"></Navbar>
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
                                            state.data?.products_data[0]
                                                ?.name
                                        }}</span
                                    >
                                    <span
                                        class="text-blue-gray-500 text-xs"
                                        v-if="
                                            state.data?.products_data
                                                .length > 1
                                        "
                                        >+{{
                                            state.data?.products_data
                                                .length - 1
                                        }}
                                        other(s)</span
                                    >
                                </div>
                                <span class="text-app-900 text-xs text-left"
                                    >by
                                    {{ state.data?.client_company }}</span
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
                                            state.data?.po_deposit_data?.invoice_status ==
                                            'progress'
                                                ? 'text-orange-500'
                                                : 'text-green-500'
                                        "
                                    >
                                        <span>{{ state.data?.po_deposit_data?.invoice_status }}</span>
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
                            </div>
                            <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoices</span
                                    >
                                    <div class="mt-1 flex justify-between w-full items-center" v-for="invoice in state.invoices">
                                <span class="text-sm">
                                    {{ invoice.invoice_number}}
                                </span>
                                <span class="text-xs">
                                    {{ invoice.invoice_date}}
                                </span>
                            </div>
                                </div>
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
                                >({{
                                    state.data?.products_data.length
                                }})</span
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
                            >History
                            <span>({{ state.progress?.length }})</span></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showProgress">
                        <template v-for="(row, i) in state.progress" :key="i">
                        <div class="py-3 border-b flex flex-col gap-2">
                            <div class="flex">
                                <span class="text-xs text-gray-400">
                                    {{
                                        dayjs(row.created_at).format(
                                            "D MMMM YYYY"
                                        )
                                    }}
                                </span>
                                <div class="flex-1"></div>
                                <span class="text-xs text-gray-400 capitalize">
                                    PIC: {{
                                        row.pic
                                    }}
                                </span>
                            </div>
                            <div
                                class="text-sm text-gray-600"
                                v-html="
                                    row.note.replace(/(?:\r\n|\r|\n)/g, '<br>')
                                "
                            ></div>
                        </div>
                    </template>
                    </div>
                    <div class="p-4 w-full mb-4" v-if="user.position == 'admin' || user.position == 'finance'">
                        <button
                            type="button"
                            class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                            @click="followup"
                        >
                            <i class="ri-add-line text-xl"></i>
                            <span class="text-sm"> Add Progress </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <InvoiceProgress
        :show="state.edit_open"
        @hide="state.edit_open = false"
        @action="handleAction"
    ></InvoiceProgress>
    
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
            () => {
                alertShowSuccess = false;
                router.back();
            }
        "
    ></Alert>
</template>
