<script setup>
import { useRouter, useRoute } from "vue-router";
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import { loading } from "../services/router";
import Navbar from "../components/Navbar.vue";
import BottomsheetInventory from "../components/BottomsheetInventory.vue";
import { deleteDelivery, getDeliveryList, nom } from "../services/service";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
const router = useRouter();
const route = useRoute();
const state = reactive({
    id: null,
    data: null,
    selected: null,
});
const confirmDelete = ref(false);
const alertShowError = ref(false);
const getDel = () => {
    getDeliveryList(state.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            filterDeliveries();
        }
    });
};
onMounted(() => {
    loading();
    state.id = route.params.id;
    getDel();
});
const tabs = [
    { label: "all", icon: "file-list-2" },
    { label: "ready", icon: "truck" },
    { label: "partial", icon: "luggage-cart" },
    { label: "delivered", icon: "checkbox-circle" },
];
const tab = ref(0);
const handleTabChange = (i) => {
    tab.value = i;
    filterDeliveries();
};
const handleDelete = () => {
    loading();
    deleteDelivery(state.selected).then((r) => {
        if (r.code === 200) {
            confirmDelete.value = false;
            state.selected = null;
            getDel();
        } else {
            alertShowError.value = true;
            state.selected = null;
        }
        loading(false);
    });
};
const filteredDeliveries = ref([]);
const filterDeliveries = () => {
    setTimeout(() => {
        filteredDeliveries.value =
            tab.value == 0
                ? state.data?.deliveries
                : state.data?.deliveries.filter(
                      (e) => e.status == tabs[tab.value].label
                  );
    }, 10);
};

const handleSelectedProject = (i) => {
    state.selected = i;
};
</script>
<template>
    <main class="flex flex-col h-full">
        <Navbar title="Delivery" ></Navbar>
        <a
            :href="`/#/details/${state.id}`"
            target="_blank"
            class="flex justify-between px-4 py-2 items-center border-b"
        >
            <div class="flex gap-1 items-center h-6">
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.data?.project?.client_po_number !== null"
                    >#PO {{ state.data?.project?.client_po_number }}</span
                >
                <span
                    class="text-xs text-blue-gray-500 font-semibold"
                    v-show="state.data?.project?.client_po_number === null"
                    >#PO Dalam Proses</span
                >
                <i
                    v-show="state.data?.project?.client_po_number === null"
                    class="ri-error-warning-fill text-lg text-app-500"
                ></i>
            </div>
            <div class="flex gap-2 items-center h-7">
                <span class="text-xs text-black"
                    >#JOB {{ state.data?.project?.job_number }}</span
                >
            </div>
        </a>
        <div class="flex-1 flex flex-col h-0">
            <div class="flex shadow-md z-10 shadow-gray-100 bg-white pt-1">
                <button
                    v-for="(item, i) in tabs"
                    @click="handleTabChange(i)"
                    :class="`reset  flex-1 flex flex-row p-2 items-center justify-center gap-2 border-b-4  ${
                        tab == i
                            ? 'text-app-500 border-b-app-500'
                            : 'border-b-transparent text-app-300 opacity-80'
                    }`"
                    :key="i"
                >
                    <i
                        :class="` text-xl ri-${
                            item.icon + (tab == i ? '-fill' : '-line')
                        }`"
                    ></i>
                    <span class="text-xs capitalize">{{ item.label }}</span>
                </button>
            </div>
            <div class="flex-1 overflow-auto">
                <div
                    class="min-h-full min-w-full flex flex-col bg-gray-50"
                    v-if="filteredDeliveries?.length"
                >
                    <div
                        v-for="(item, i) in filteredDeliveries"
                        :key="i"
                        @click="handleSelectedProject(item.id)"
                    >
                        <div class="rounded-none bg-white border-b">
                            <div class="flex gap-1 justify-start">
                                <div
                                    :class="`w-20 flex-shrink-0 flex items-center justify-center flex-col  ${
                                        item.status == 'partial'
                                            ? 'bg-blue-100 text-blue-600 border-blue-600'
                                            : item.status == 'ready'
                                            ? 'bg-yellow-100 text-yellow-600 border-yellow-600'
                                            : item.status == 'delivered'
                                            ? 'bg-green-100 text-green-600 border-green-600'
                                            : ''
                                    }`"
                                >
                                    <span class="text-3xl font-bold">{{
                                        dayjs(item.delivery_date).format("DD")
                                    }}</span>
                                    <span class="text-sm">{{
                                        dayjs(item.delivery_date).format(
                                            "MMM YYYY"
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="flex-1 flex flex-col items-start p-2"
                                >
                                    <div
                                        class="flex justify-between items-center w-full"
                                    >
                                        <span class="font-semibold text-black text-sm"
                                            ># {{ item.do_number }}</span
                                        >

                                        <div
                                            :class="` rounded-full text-xs  items-center justify-center flex capitalize ${
                                                item.status == 'partial'
                                                    ? 'text-blue-600 '
                                                    : item.status == 'ready'
                                                    ? '  text-yellow-600 '
                                                    : item.status == 'delivered'
                                                    ? '  text-green-600 '
                                                    : ''
                                            }`"
                                        >
                                            {{ item.status }}
                                        </div>
                                    </div>
                                    <span class="text-xs text-blue-gray-500"
                                        >{{ nom(item.delivered ?? 0) }}/{{
                                            nom(item.total)
                                        }}
                                        items delivered</span
                                    >
                                    <div class="text-xs">
                                        {{ item.default_origin_data?.name }}
                                        <span class="text-gray-500"> to </span>
                                        {{ item.destination_data?.name }}
                                        <br>
                                        <span class="text-gray-500">
                                            by
                                        </span>
                                        {{ item.shipping_vendor_data?.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                    v-else
                >
                    <i class="ri-inbox-line text-100px text-gray-200"></i>
                    <span class="text-sm font-medium text-gray-400"
                        >No Delivery Available</span
                    >
                </div>
            </div>
        </div>
        <div
            class="px-4 py-2 flex items-center justify-center w-full border-t"
            v-if="state.data?.project.status !== 'delivered'"
        >
            <button
                class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                @click="router.push('/delivery/add/' + state.id)"
            >
                <i class="ri-add-line text-xl"></i>
                <span class="text-sm mt-1"> Add Delivery Order </span>
            </button>
        </div>
    </main>

    <BottomsheetInventory
        :show="state.selected != null"
        :selected="state.selected"
        :items="state.data?.deliveries"
        @hide="state.selected = null"
        @delete="confirmDelete = true"
    ></BottomsheetInventory>

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected project?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="(confirmDelete = false), (state.selected = null)"
        @fire="handleDelete"
    >
    </Confirm>

    <Alert
        type="fail"
        title="Failed to delete"
        :content="'Failed to delete delivery'"
        :show="alertShowError != false"
        @hide="alertShowError = false"
    ></Alert>
</template>
