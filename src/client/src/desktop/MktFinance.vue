<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    createProgress,
    getProgress,
    nom
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";

const user = currentUser.user?.user;
const confirmDelete = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    data: null,
    progress: [],
    errors: {},
    project_id: null,
    invoice_date: null,
    invoice_number: null,
    invoices: [],
    pic: null,
    status: null,
    note: null,
});

const submit = () => {
    loading();
    let data = {
        project: state.project_id,
        invoice_date: state.invoice_date,
        invoice_number: state.invoice_number,
        pic: state.pic,
        status: state.status,
        note: state.note,
    };
    createProgress(state.project_id, data).then((r) => {
        loading(false);
        if (r.code === 200) {
            alertShowSuccess.value = true;
        } else {
            state.errors = r.errors;
            alertShowFailed.value = true;
        }
    });
};
onMounted(() => {
    loading();
    let id = currentUrl.split("/").slice(-1);
    getProject(id).then((r) => {
        loading(false);
        if (r.code === 200) {
            let data = r.data;
            state.data = data;
            state.project_id = data?.id;
            state.invoice_date = data?.invoice_date;
            state.invoice_number = data?.invoice_number;
            state.status = data?.invoice_status;
            state.invoices = data?.po_deposit_data?.invoices
                ? JSON.parse(data?.po_deposit_data?.invoices)
                : null;
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
    <div class="p-8">
        <div
            class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center"
        >
            Invoice Progress
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
                        :class="`border  rounded-full text-xs px-3 py-1 items-center justify-center flex capitalize ${
                            state.data?.status == 'ready'
                                ? 'bg-blue-100 text-blue-600 border-blue-600'
                                : state.data?.status == 'partial'
                                ? 'bg-yellow-100 text-yellow-600 border-yellow-600'
                                : state.data?.status == 'delivered'
                                ? 'bg-green-100 text-green-600 border-green-600'
                                : state.data?.status == 'cancel'
                                ? 'bg-red-100 text-red-600 border-red-600'
                                : ''
                        }`"
                    >
                        {{ state.data?.status }}
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
                        <div class="flex-1 flex flex-col items-end">
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
                                >Invoice Status</span
                            >
                            <strong
                                class="mt-1 capitalize"
                                :class="
                                    state.data?.po_deposit_data
                                        ?.invoice_status == 'progress'
                                        ? 'text-orange-500'
                                        : 'text-green-500'
                                "
                                >{{
                                    state.data?.po_deposit_data?.invoice_status
                                }}</strong
                            >
                        </div>
                    </div>
                    <div class="flex py-2">
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
                                    {{ invoice.invoice_date }}
                                </span>
                            </div>
                        </div>
                        <!-- <div class="flex flex-col items-end flex-1">
                            <span class="text-xs text-blue-gray-500"
                                >Invoice Date</span
                            >
                            <strong class="mt-1">{{
                                state.data?.invoice_date
                                    ? dayjs(state.data?.invoice_date).format(
                                          "D MMMM YYYY"
                                      )
                                    : "-"
                            }}</strong>
                        </div> -->
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
                        >Progress</strong
                    >
                </div>

                <form
                    autocomplete="off"
                    @submit.prevent="submit"
                    class="px-3"
                    av-if="state.data?.invoice_status != 'sent'"
                    v-if="user.position == 'finance' || user.position == 'admin'"

                >
                    <div class="flex gap-4 w-full">
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
                                    >{{ state.errors?.invoice_number[0] }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-2 flex-1">
                            <span class="text-sm text-left">Invoice Date</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="date"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    onfocus="this.showPicker()"
                                    v-model="state.invoice_date"
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-calendar-event-line"></i>
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
                                    >{{ state.errors?.invoice_date[0] }}</span
                                >
                            </div>
                        </label>
                    </div>
                    <hr class="mt-2 mb-4" />
                    <div class="flex gap-4 w-full">
                        <label class="flex flex-col gap-2 flex-1">
                            <span class="text-sm text-left">PIC</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center pr-2"
                            >
                                <select
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    v-model="state.pic"
                                >
                                    <option value="marketing">Marketing</option>
                                    <option value="finance">Finance</option>
                                    <option value="delivery">Delivery</option>
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
                                    v-if="state.errors.hasOwnProperty('pic')"
                                    >{{ state.errors?.pic[0] }}</span
                                >
                            </div>
                        </label>

                        <label class="flex flex-col gap-2 flex-1">
                            <span class="text-sm text-left">Status</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center pr-2"
                            >
                                <select
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    v-model="state.status"
                                >
                                    <option value="progress">Progress</option>
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
                                    v-if="state.errors.hasOwnProperty('status')"
                                    >{{ state.errors?.status[0] }}</span
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
                                v-if="state.errors.hasOwnProperty('note')"
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

                <strong
                    class="text-sm text-blue-gray-500 my-4 flex bg-gray-100 px-3 py-2"
                    >History
                    <span>&nbsp;({{ state.progress.length }})</span></strong
                >
                <div class="px-3">
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
                                    PIC: {{ row.pic }}
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
                router.push('/desktop/projects');
            }
        "
    ></Alert>
</template>
