<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import {
    getProject,
    getInventoriess,
    getLogsList,
    nom,
    ASSETSURL
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
const router = useRouter();
const route = useRoute();
const currentUrl = route.path;
const showProduct = ref(false);
const showDelivery = ref(false);
const showSJ = ref(false);
const state = reactive({
    data: [],
    project: null,
    inventory: [],
    deliveries:[],
    id: null,
    viewFile:null
});

onMounted(() => {
    loading();
    state.id = currentUrl.split("/").slice(-1);
    getInventoriess(state.id).then((r) => {
        if (r.code === 200) {
            state.inventory = r.data;
            getProject(state.id).then((rr) => {
                if (rr.code === 200) {
                    state.project = rr.data;
                    state.project.products_data.map((p) => {
                        console.log(p.description);
                        p.description = p.description?.replace(
                            /(?:\r\n|\r|\n)/g,
                            "<br>"
                        );
                        p.stored = state.inventory
                            .filter(
                                (e) =>
                                    e.quantity > 0 &&
                                    e.product === p.id &&
                                    e.warehouse !== 2
                            )
                            .map(
                                (e) =>
                                    `<span>${e.warehouse_data?.name} (${nom(
                                        e.quantity
                                    )})</span>`
                            )
                            .join(" | ");
                        p.delivered = Number(
                            state.inventory
                                .filter(
                                    (e) =>
                                        e.quantity > 0 &&
                                        e.product === p.id &&
                                        e.warehouse === 2
                                )
                                .map((e) => `${nom(e.quantity)}`)
                                .join("")
                        );
                        p.status =
                            p.delivered == 0
                                ? "ready"
                                : p.delivered >= p.quantity
                                ? "delivered"
                                : "partial";
                        return p;
                    });
                }
                loading(false);
            });
        }
    });
    getLogsList(state.id).then((r) => {
        if (r.code === 200) {
            state.data = r.data;
            state.deliveries = r.dels.map((e) => {
                e.do_files = JSON.parse(e.do_files ?? '[]');
                if (e.do_file) {
                    e.do_files.push(e.do_file);
                }
                return e;
            });
        }
    });
});
</script>
<template>
    <main class="flex flex-col h-full text-black">
        <Navbar title="Delivery History" back="/delivery-report/?filter=true"></Navbar>
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
                                        state.project?.client_po_number !== null
                                    "
                                    >#PO
                                    {{ state.project?.client_po_number }}</span
                                >
                                <span
                                    class="text-xs text-blue-gray-500 font-semibold"
                                    v-show="
                                        state.project?.client_po_number === null
                                    "
                                    >#PO Dalam Proses</span
                                >
                                <i
                                    v-show="
                                        state.project?.client_po_number === null
                                    "
                                    class="ri-error-warning-fill text-lg text-app-500"
                                ></i>
                            </div>
                            <div class="flex gap-2 items-center h-7">
                                <span class="text-xs text-black"
                                    >#JOB {{ state.project?.job_number }}</span
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
                                        >{{ state.project?.title }}</span
                                    >
                                    <span
                                        class="font-semibold text-black text-sm pr-2 leading-4"
                                        >{{
                                            state.project?.products_data[0]
                                                ?.name
                                        }}</span
                                    >
                                    <span
                                        class="text-blue-gray-500 text-xs"
                                        v-if="
                                            state.project?.products_data
                                                .length > 1
                                        "
                                        >+{{
                                            state.project?.products_data
                                                .length - 1
                                        }}
                                        other(s)</span
                                    >
                                </div>
                                <span class="text-app-900 text-xs text-left"
                                    >by
                                    {{ state.project?.client_company }}</span
                                >
                            </div>
                            <div
                                class="rounded-full px-2 py-2px text-10px font-medium text-white capitalize"
                                :class="
                                    state.project?.status === 'delivered'
                                        ? 'bg-[#6FD669]'
                                        : state.project?.status === 'partial'
                                        ? 'bg-[#4DC8E3]'
                                        : state.project?.status === 'ready'
                                        ? 'bg-[#E5C100]'
                                        : state.project?.status === 'cancel'
                                        ? 'bg-[#E44545]'
                                        : state.project?.status === 'production'
                                        ? 'bg-[#3f51b5]'
                                        : ''
                                "
                            >
                                {{ state.project?.status }}
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
                                        <strong>{{ state.project?.total }}</strong
                                        >/<span>{{ state.project?.total }}</span>
                                    </div>
                                </div> -->
                                <div class="flex-1 flex flex-col items-start">
                                    <span class="text-xs text-blue-gray-500"
                                        >Stored</span
                                    >
                                    <div class="text-black text-sm">
                                        <strong>{{
                                            nom(state.project?.stored ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.project?.total)
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
                                            nom(state.project?.delivered ?? 0)
                                        }}</strong
                                        >/<span>{{
                                            nom(state.project?.total)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col items-end">
                                    <span class="text-xs text-blue-gray-500"
                                        >Invoice</span
                                    >
                                    <div
                                        class="text-xs font-medium capitalize"
                                        :class="
                                            state.project?.invoice_status ==
                                            'progress'
                                                ? 'text-orange-500'
                                                : 'text-green-500'
                                        "
                                    >
                                        <span>{{
                                            state.project?.invoice_status
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
                                        state.project?.pic_name
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-end flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Client's PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.project?.client_pic_name
                                    }}</strong>
                                </div>
                            </div>
                            <div class="flex py-2 justify-between">
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Tanggal PO</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.project?.client_po_date
                                            ? dayjs(
                                                  state.project?.client_po_date
                                              ).format("D MMMM YYYY")
                                            : "Dalam Proses"
                                    }}</strong>
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
                                    state.project?.products_data.length
                                }})</span
                            ></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showProduct">
                        <div
                            class="flex flex-col py-3 border-b"
                            v-for="(item, i) in state.project?.products_data"
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
                        @click="showSJ = !showSJ"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Surat Jalan
                            <span>({{ state.deliveries?.length }})</span></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showSJ">
                        <div
                            class="flex flex-col py-3 border-b"
                            v-for="(item, i) in state.deliveries"
                            :key="i"
                        >
                            <div class="flex justify-between text-sm gap-2">
                                <span>
                                    {{ item?.do_number }}
                                </span>
                                <span>
                                    {{
                                        dayjs(item.delivery_date).format(
                                            "DD MMM YYYY"
                                        )
                                    }}
                                </span>
                            </div>
                            <div class="flex gap-2 overflow-x-auto py-2"
                                v-if="item?.do_files?.length">
                                <div class="flex items-center w-60px h-60px  flex-shrink-0 rounded border relative"
                                    v-for="(file, i) in item?.do_files" @click="state.viewFile = file">
                                    <img :src="ASSETSURL + file" alt="" class="w-full h-full object-cover">
                                </div>
                            </div>

                        </div>
                    </div>
                    <button
                        class="px-4 py-3 flex justify-between items-center border-b"
                        @click="showDelivery = !showDelivery"
                    >
                        <strong class="text-sm text-blue-gray-500"
                            >Delivery
                            <span>({{ state.data?.length }})</span></strong
                        >
                        <i class="ri-expand-up-down-line"></i>
                    </button>
                    <div class="px-4 bg-white shadow-sm" v-show="showDelivery">
                        <div
                            class="flex flex-col py-3 border-b"
                            v-for="(item, i) in state.data"
                            :key="i"
                        >
                            <div class="flex justify-between text-sm gap-2">
                                <span>
                                    {{ item.delivery_data?.do_number }}
                                </span>
                                <span>
                                    {{
                                        dayjs(item.delivered_at).format(
                                            "DD MMM YYYY"
                                        )
                                    }}
                                </span>
                            </div>
                            <div class="flex justify-between text-sm gap-2">
                                <strong class="leading-4">{{
                                    item.product_data?.name
                                }}</strong>
                                <span class="flex-shrink-0 whitespace-nowrap">
                                    {{ nom(item.actual_quantity) }}</span
                                >
                            </div>
                            <div class="flex justify-between text-sm gap-2">
                                <span>
                                    {{ item.origin_data?.name}}
                                </span>
                                <span class="text-gray-400">
                                    to
                                </span>
                                <span>
                                    {{ item.destination_data?.name}}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <div class="fixed bottom-0 left-0 right-0 top-0 z-60 bg-black/70 flex items-center justify-center" v-if="state.viewFile">
        <img :src="ASSETSURL + state.viewFile" alt="" class="max-w-full max-h-full object-contain">
        <i class="ri-close-circle-fill text-red-500 text-4xl absolute top-2 w-32px h-32px block flex items-center justify-center right-2 bg-white rounded-full"
            @click="state.viewFile = null"></i>

    </div>
</template>
