<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import { useRoute, useRouter } from "vue-router";
import {
    deleteClient,
    getClientList,
} from "../services/service";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";

const confirmDelete = ref(false);
const router = useRouter();
const state = reactive({
    filter_open: false,
    selected: [],
    data: [],
    meta:null,
    search:null,
    limit: 20,
    page: 1,
});

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
const deleteSelectedClient = () => {
    confirmDelete.value = false;
    loading();
    const deletePromises = state.selected.map((user) =>
        deleteClient(user)
    );
    Promise.all(deletePromises).finally(() => {
        search();
    });
};
const setDelete = (id) => {
    state.selected = [id];
    confirmDelete.value = true;
};
const search = () => {
    loading()
    let filter = {
        search: state.search,
        page: state.page,
        limit: state.limit,
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

const doSearch = () => {
    state.page = 1
    search()
}
onMounted(() => {
    doSearch();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Manage Clients
        </div>
        <div class="flex justify-between mb-6">
            <div class="flex gap-2">
                <button
                    class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    @click="
                        () => router.push('/desktop/clients/add')
                    "
                >
                    <i class="ri-add-line text-xl"></i>
                    <strong>Add Client</strong>
                </button>
            </div>
            <div>
                <form
                    class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300"
                    @submit.prevent="doSearch"
                >
                    <input
                        type="text"
                        class="h-full w-full rounded-full bg-transparent pl-45px"
                        placeholder="Search"
                        v-model="state.search"
                    />
                    <i
                        class="ri-search-line absolute left-4 text-xl text-[#667085]"
                    ></i>
                </form>
            </div>
        </div>
        <div class="w-full bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full h-full border-b">
                <thead class="bg-[#F9FAFB] border-b text-[#8A92A6] text-12px">
                    <tr>
                        <th class="p-1">
                            <div
                                class="rounded flex items-center justify-start py-1 min-h-10 px-1 gap-2 w-full cursor-default group"
                            >
                                <span class="font-bold leading-4">Name</span>
                            </div>
                        </th>
                        <th class="p-1"></th>
                    </tr>
                </thead>
                <tbody class="text-13px">
                    <tr
                        class="border-b"
                        v-for="(row, i) in state.data"
                        :key="i"
                    >
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1"
                            >
                                {{ row.name }}
                            </div>
                        </td>
                        <td class="p-1 w-60px">
                            <div
                                class="flex items-center justify-start"
                            >
                                <router-link
                                    :to="
                                        '/desktop/clients/edit/' +
                                        row.id
                                    "
                                >
                                    <div
                                        class="text-xl p-1 text-[#667085] hover:bg-gray-100 rounded"
                                    >
                                        <i class="ri-pencil-line"></i>
                                    </div>
                                </router-link>
                                <button
                                    @click="setDelete(row.id)"
                                    class="text-xl p-1 text-red-500 hover:bg-gray-100 rounded"
                                >
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="flex justify-between px-6 py-2 items-center">
                <button
                    class="text-sm border rounded-md px-4 py-2 bg-white"
                    @click="prevPage"
                    :class="
                        state.page <= 1
                            ? 'filter brightness-90 cursor-default text-gray-400'
                            : 'hover:bg-gray-50'
                    "
                >
                    Previous
                </button>
                <div class="text-sm">
                    Page {{ state.page }} of {{ state.meta?.total_pages || 1 }}
                </div>
                <button
                    class="text-sm border rounded-md px-4 py-2 bg-white"
                    @click="nextPage"
                    :class="
                        state.page >= state.meta?.total_pages
                            ? 'filter brightness-90 cursor-default text-gray-400'
                            : 'hover:bg-gray-50'
                    "
                >
                    Next
                </button>
            </div>
        </div>
    </div>

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected client?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedClient"
    >
    </Confirm>
</template>
