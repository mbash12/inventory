<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref } from "vue";
import {
    getProject,
    getFollowup,
    updateFollowup,
    createFollowup,
    nom,
    currentUser,
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
    is_plan: false,
    history: [],
    errors: {},
    data: null,
    id: null,
    project_id: null,
    client_po_date: null,
    client_po_number: null,
    schedule_date: null,
    note: null,
});

const submit = () => {
    loading();
    let data = {
        id: state.id,
        project: state.project_id,
        client_po_date: state.client_po_date,
        client_po_number: state.client_po_number,
        schedule_date: state.schedule_date,
        note: state.note,
    };
    if (state.id === null) {
        createFollowup(state.project_id, data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                state.errors = r.errors;
                alertShowFailed.value = true;
            }
        });
    } else {
        updateFollowup(state.project_id, data).then((r) => {
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
            state.client_po_number = data.client_po_number;
            state.client_po_date = dayjs(data.client_po_date).format(
                "YYYY-MM-DD"
            );
        }
    });

    getFollowup(id).then((rr) => {
        if (rr.code === 200) {
            state.history = rr.data;
            let fu = rr.data.find((e) => e.followup_date == null);
            if (fu) {
                state.id = fu.id;
                state.note = fu.note;
                state.schedule_date = dayjs(fu.schedule_date).format(
                    "YYYY-MM-DD"
                );
            }
        }
    });
});
</script>

<template>
    <div class="p-8">
        <div
            class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center"
        >
            PO Action
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
                        >Follow Up</strong
                    >
                </div>
                <form
                    autocomplete="off"
                    @submit.prevent="submit"
                    class="px-3"
                    v-if="
                        (user.position == 'marketing' || user.position == 'admin') && state.data?.client_po_number == null
                    "
                >
                    <div class="flex gap-4 w-full">
                        <div class="flex w-full gap-2" v-if="state.id == null">
                            <div
                                class="flex-1 p-2 border rounded flex items-center justify-center text-sm"
                                @click="state.is_plan = true"
                                :class="
                                    state.is_plan
                                        ? 'border-red-500'
                                        : 'border-transparent'
                                "
                            >
                                Plan
                            </div>
                            <div
                                class="flex-1 p-2 border rounded flex items-center justify-center text-sm"
                                @click="state.is_plan = false"
                                :class="
                                    !state.is_plan
                                        ? 'border-red-500'
                                        : 'border-transparent'
                                "
                            >
                                Follow Up
                            </div>
                        </div>
                    </div>
                    <br />
                    <div
                        class="flex gap-4 w-full"
                        v-if="!state.is_plan || state.id != null"
                    >
                        <label class="flex flex-col gap-2 flex-1">
                            <span class="text-sm text-left">PO Number</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    v-model="state.client_po_number"
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
                                        state.errors?.client_po_number[0]
                                    }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-2 flex-1">
                            <span class="text-sm text-left">PO Date</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="date"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    onfocus="this.showPicker()"
                                    v-model="state.client_po_date"
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
                                            'client_po_date'
                                        )
                                    "
                                    >{{ state.errors?.client_po_date[0] }}</span
                                >
                            </div>
                        </label>
                    </div>
                    <hr
                        class="mt-2 mb-4"
                        v-if="!state.is_plan || state.id != null"
                    />
                    <label class="flex flex-col gap-2">
                        <span class="text-sm text-left">Plan Date</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="date"
                                class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                :class="state.id !== null ? 'bg-gray-50' : ''"
                                onfocus="this.showPicker()"
                                v-model="state.schedule_date"
                                :disabled="state.id !== null"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-calendar-event-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="
                                    state.errors?.hasOwnProperty('schedule_date')
                                "
                                >{{ state.errors?.schedule_date[0] }}</span
                            >
                        </div>
                    </label>
                    <label class="flex flex-col gap-2">
                        <span class="text-sm text-left">Note</span>
                        <div
                            class="w-full border rounded-lg bg-white relative flex items-center"
                        >
                            <textarea
                                class="bg-transparent w-full h-full rounded-lg p-4 noicon text-14px h-100px"
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
                    <div class="flex gap-2 justify-end">
                        <button
                            class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                            v-if="!state.is_plan || state.id != null"
                        >
                            <i class="ri-save-line text-xl"></i>
                            <strong> Save </strong>
                        </button>
                        <button
                            class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                            v-if="state.is_plan"
                        >
                            <i class="ri-save-line text-xl"></i>
                            <strong> Plan </strong>
                        </button>
                    </div>
                </form>

                <strong
                    class="text-sm text-blue-gray-500 my-4 flex bg-gray-100 px-3 py-2"
                    >History
                    <span>&nbsp;({{ state.history.length }})</span></strong
                >
                <div class="px-3">
                    <template v-for="(row, i) in state.history" :key="i">
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
                            </div>
                            <div
                                class="text-sm text-gray-600"
                                v-html="
                                    row.note?.replace(/(?:\r\n|\r|\n)/g, '<br>')
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
