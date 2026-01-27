<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import { useRoute, useRouter } from "vue-router";
import { getLog, nom } from "../services/service";
import { loading } from "../services/router";
const router = useRouter();
const route = useRoute();
const state = reactive({
    data: null,
});
onMounted(() => {
    loading();
    state.id = route.params.id;
    getLog(state.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
        }
    });
});
</script>
<template>
    <main class="flex flex-col h-full text-black">
        <Navbar
            title="Inventory Report"
            :back="'/inventory/' + state.id"
        ></Navbar>
        <div class="flex-1 flex flex-col">
            <div class="flex-1 overflow-auto">
                <div class="min-h-full min-w-full flex flex-col bg-gray-50">
                    <div
                        class="rounded-none bg-white shadow-md shadow-gray-100"
                    >
                        <div class="px-4 pb-2 flex flex-col gap-2 py-4"> 
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Job Number</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.delivery_data.project_data
                                            .job_number
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >PO Number</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.delivery_data.project_data
                                            .client_po_number ??
                                        "PO Dalam Proses"
                                    }}</strong>
                                </div>
                            
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Origin</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.origin_data.name
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Destination</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.destination_data.name
                                    }}</strong>
                                </div>
                            
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Item</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.product_data.name
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Quantity</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        nom(state.data?.actual_quantity ?? 0)
                                    }}</strong>
                                </div>
                            
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Client</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.delivery_data.project_data
                                            .client_company
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >PIC</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.delivery_data.project_data
                                            .client_pic_name
                                    }}</strong>
                                </div>
                            
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >PO Date</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        state.data?.delivery_data?.project_data?.client_po_date
                                            ? dayjs(
                                                  state.data?.delivery_data?.project_data?.client_po_date
                                              ).format("D MMMM YYYY")
                                            : "Dalam Proses"
                                    }}</strong>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-xs text-blue-gray-500"
                                        >Delivery Date</span
                                    >
                                    <strong class="mt-1 text-sm">{{
                                        dayjs(state.data?.delivered_at).format(
                                            "D MMMM YYYY"
                                        )
                                    }}</strong>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
