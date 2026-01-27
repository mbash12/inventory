<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from "dayjs";
import Navbar from "../components/Navbar.vue";
import { useRoute, useRouter } from "vue-router";
import { getLogList, nom } from "../services/service";
import { loading } from "../services/router";
const router = useRouter();
const route = useRoute();
const state = reactive({
    data: null,
});
onMounted(() => {
    loading();
    state.id = route.params.id;
    getLogList(state.id).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
        }
    });
});
</script>
<template>
    <main class="flex flex-col h-full">
        <Navbar title="Inventory History" back="/inventories/?filter=true"></Navbar>
        <div class="flex-1 flex flex-col">
            <div class="flex-1 overflow-auto">
                <div
                    class="min-h-full min-w-full flex flex-col bg-gray-50"
                    v-if="state.data?.length"
                >
                    <div
                        v-for="(item, i) in state.data"
                        :key="i"
                        @click="router.push('/inventory/details/' + item.id)"
                    >
                        <div
                            class="rounded-none bg-white border-b cursor-pointer"
                        >
                            <div class="flex gap-1 justify-center items-stretch">
                                <div
                                    :class="`w-20 flex-shrink-0 flex items-center justify-center flex-col ${
                                        item.direction == 'in'
                                            ? 'bg-green-100 text-green-600'
                                            : 'bg-red-100 text-red-600'
                                    }`"
                                >
                                    <span class="text-3xl font-bold">{{
                                        dayjs(item.delivered_at).format("DD")
                                    }}</span>
                                    <span class="text-sm">{{
                                        dayjs(item.delivered_at).format(
                                            "MMM YYYY"
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="flex-1 flex flex-col items-start p-2"
                                >
                                    <div
                                        :class="`rounded-full text-xs items-center justify-center flex text-blue-600`"
                                    >
                                        {{
                                            item.direction === "in"
                                                ? item.destination_data.name
                                                : item.origin_data.name
                                        }}'s Warehouse
                                    </div>
                                    <span class="font-semibold text-black text-sm leading-4">{{
                                        item.product_data.name
                                    }}</span>
                                    <span class="text-sm text-blue-gray-500">
                                        {{ nom(item.actual_quantity) }} </span
                                    >
                                </div>
                                <div
                                    :class="`items-center justify-center p-4 text-lg  font-bold uppercase ${
                                        item.direction == 'in'
                                            ? 'text-green-600'
                                            : 'text-red-600'
                                    }`"
                                >
                                    <span>{{ item.direction }}</span>
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
                        >No History Available</span
                    >
                </div>
            </div>
        </div>
    </main>
</template>
