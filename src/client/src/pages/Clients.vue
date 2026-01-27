<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import Bottomsheet from "../components/Bottomsheet.vue";
import { loading } from "../services/router";
import { getClientList, getWarehouseList, nom, goto, deleteClient } from "../services/service";
import BottomsheetProjectFilter from "../components/BottomsheetProjectFilter.vue";
import Table from "../components/Table.vue";
import Confirm from "../components/Confirm.vue";

import BottomSheetAction from "../components/BottomSheetAction.vue";
const state = reactive({
    data: [],
    meta: {},
    filter_open: false,
    search: "",
    page: 1,
    export: null,
    export_mode: "print",
});
const confirmDelete = ref(false);
const handleDelete = () => {
    loading();
    deleteClient(state.selected).then((r) => {
        if (r.code === 200) {
            confirmDelete.value = false;
            state.selected = null;
            search();
        } else {
            alertShowError.value = true;
            state.selected = null;
        }
        loading(false);
    });
};
const handleFilter = (e) => {
    state.status = e.status;
    state.po = e.po;
    state.start_date = e.start_date;
    state.end_date = e.end_date;
    state.filter_open = false;
    search();
};
const handleClearFilter = () => {
    state.status = [];
    state.po = null;
    state.start_date = null;
    state.end_date = null;
    state.filter_open = false;
    search();
};
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
const doSearch = () => {
    state.page = 1;
    state.selected = null;
    search();
};
const search = () => {
    loading();
    let filter = {
        limit:20,
        search: state.search,
        page: state.page,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    getClientList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }
    });
};

const prepareExport = (mode) => {
   
};
onMounted(() => {
    doSearch();
});
const submit = (e) => {
    e.preventDefault();
    doSearch();
};
</script>

<template>
    <div class="flex-1 flex flex-col min-h-0 h-full">
        <div
            class="flex shadow-md z-2 shadow-gray-100 bg-white px-2 py-1 gap-1"
        >
            <form
                @submit="submit"
                class="flex-1 flex items-center rounded-full overflow-hidden bg-gray-100 relative"
            >
                <input
                    type="search"
                    placeholder="Search..."
                    name=""
                    id=""
                    class="h-9 flex-1 text-light bg-transparent text-black rounded-full px-4 w-full"
                    v-model="state.search"
                />
                <button
                    class="h-9 w-9 flex items-center justify-center text-app-500 absolute right-1"
                    @click="doSearch"
                >
                    <i class="ri-search-line"></i>
                </button>
            </form>
            <!-- <button
                class="h-9 w-9 flex items-center justify-center text-app-500"
                @click="state.filter_open = true"
            >
                <i class="ri-filter-line"></i>
            </button> -->
        </div>
        <div class="flex-1 overflow-auto">
            <div
                class="min-h-full min-w-full flex flex-col bg-gray-50"
                v-if="state.data.length"
            >
                <div
                    class="rounded-none bg-white border-b-2"
                    v-for="item in state.data"
                    :key="item.id"
                >
                    
                <div
                            @click="state.selected = item.id"
                            class="flex justify-between px-4 py-4 items-center"
                        >
                            <span class="text-sm">{{ item.name }}</span>
                        </div>
                </div>
            </div>
            <div
                class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2"
                v-else
            >
                <i class="ri-inbox-line text-100px text-gray-200"></i>
                <span class="text-sm font-medium text-gray-400"
                    >No Project Available</span
                >
            </div>
        </div>
        <div
            class="h-40px bg-gray-100 shadow-lg w-full border-t flex items-center px-1"
        >
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page <= 1
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="prevPage"
            >
                <i class="ri-skip-left-line"></i>
                <span class="text-xs">Prev</span>
            </div>
            <div class="flex-1 items-center justify-center flex text-sm">
                Page {{ state.page }} of {{ state.meta?.total_pages || 1 }}
            </div>
            <div
                class="px-4 h-35px rounded border flex items-center justify-center bg-white"
                :class="
                    state.page >= state.meta?.total_pages
                        ? 'filter brightness-90 cursor-default text-gray-400'
                        : 'hover:bg-gray-50'
                "
                @click="nextPage"
            >
                <span class="text-xs">Next</span>
                <i class="ri-skip-right-line"></i>
            </div>
        </div>
    </div>
    <div class="fixed bottom-12 right-2 w-12 h-12 bg-red-500 rounded-full shadow flex items-center justify-center text-white text-2xl font-bold" @click="goto('/clients/add')">
        <i class="ri-add-line"></i>
    </div>
    <BottomSheetAction
        :show="state.selected != null"
        @hide="state.selected = null"
        @action="(e)=>e=='delete' ? confirmDelete = true : null"
        :actions="[
            {title:'Edit',link:'/clients/edit/'+state.selected},
            {title:'Delete',action:'delete', color: 'red', icon: 'ri-delete-bin-line'},
        ]"
    />
    <BottomsheetProjectFilter
        :show="state.filter_open"
        :status="state.status"
        :po="state.po"
        :start_date="state.start_date"
        :end_date="state.end_date"
        @hide="state.filter_open = false"
        @apply="handleFilter"
        @clear="handleClearFilter"
    ></BottomsheetProjectFilter>

    <Table
        :data="state.export"
        :action="state.export_mode"
        @close="state.export = null"
    />

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected client?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="(confirmDelete = false), (state.selected = null)"
        @fire="handleDelete"
    >
    </Confirm>

</template>
