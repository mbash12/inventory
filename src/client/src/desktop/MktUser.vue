<script setup>
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import { useRoute, useRouter } from "vue-router";
import { getUserList, deleteUser } from "../services/service";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";

const confirmDelete = ref(false);
const router = useRouter();
const state = reactive({
    filter_open: false,
    selected: [],
    search: "",
    data: [],
    meta: {},
    order_by: null,
    sort: null,
    page: 1,
    total_page: 10,
});

const deleteSelectedUser = () => {
    confirmDelete.value = false;
    loading();
    const deletePromises = state.selected.map((user) => deleteUser(user));
    Promise.all(deletePromises).finally(() => {
        search();
    });
};
const setDelete = (id) => {
    state.selected = [id];
    confirmDelete.value = true;
};
const prevPage = () => {
    state.page = state.page - 1 >= 1 ? state.page - 1 : state.page;
};
const nextPage = () => {
    state.page =
        state.page + 1 <= state.total_page ? state.page + 1 : state.page;
};
const setSort = (field) => {
    if (state.order_by == field && state.sort == "asc") {
        state.sort = "desc";
    } else if (state.order_by == field && state.sort == "desc") {
        state.order_by = null;
        state.sort = null;
    } else {
        state.order_by = field;
        state.sort = "asc";
    }
    search();
};
const handleSelect = (e, id) => {
    if (e.target.checked) {
        state.selected = [...state.selected, id];
    } else {
        state.selected = state.selected.filter((e) => e != id);
    }
};
const handleSelectAll = (e) => {
    if (e.target.checked) {
        state.selected = state.data.map((e) => e.id);
    } else {
        state.selected = [];
    }
};
const search = () => {
    state.selected = [];
    loading();
    let filter = {
        search: state.search,
        order_by: state.order_by,
        sort: state.sort,
        page: state.page,
    };
    Object.keys(filter).forEach((key) => {
        if (filter[key] === null || filter[key] === "") {
            delete filter[key];
        }
    });
    getUserList(filter).then((r) => {
        loading(false);
        if (r.code === 200) {
            state.data = r.data;
            state.meta = r.meta;
        }
    });
};

onMounted(() => {
    search();
});
</script>

<template>
    <div class="p-8">
        <div class="text-2xl text-[#001737] font-semibold mb-6 text-left">
            Manage Users
        </div>
        <div class="flex justify-between mb-6">
            <div class="flex gap-2">
                <button
                    class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                    @click="() => router.push('/desktop/users/add')"
                >
                    <i class="ri-add-line text-xl"></i>
                    <strong>Add User</strong>
                </button>

                <button
                    v-show="state.selected.length"
                    class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-red-500 hover:shadow-sm hover:bg-gray-50 flex items-center"
                    @click="confirmDelete = true"
                >
                    <i class="ri-delete-bin-line text-xl"></i>
                    <strong>Delete Selected</strong>
                </button>
            </div>
            <div>
                <form
                    class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300"
                    @submit.prevent="search"
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
                            <label class="flex items-center justify-center">
                                <input
                                    type="checkbox"
                                    @input="handleSelectAll"
                                />
                            </label>
                        </th>
                        <th class="p-1">
                            <button
                                @click="() => setSort('email')"
                                class="rounded flex items-center justify-start py-1 min-h-10 p-1 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4">Account</span>
                                <i
                                    :class="
                                        state.order_by === 'email'
                                            ? state.sort === 'ASC'
                                                ? 'opacity-100'
                                                : 'opacity-100 rotate-180'
                                            : 'opacity-30'
                                    "
                                    class="ri-arrow-down-line text-lg font-thin transform"
                                ></i>
                            </button>
                        </th>
                        <th class="p-1">
                            <button
                                @click="() => setSort('name')"
                                class="rounded flex items-center justify-start py-1 min-h-10 p-1 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4">Name</span>
                                <i
                                    :class="
                                        state.order_by === 'name'
                                            ? state.sort === 'ASC'
                                                ? 'opacity-100'
                                                : 'opacity-100 rotate-180'
                                            : 'opacity-30'
                                    "
                                    class="ri-arrow-down-line text-lg font-thin transform"
                                ></i>
                            </button>
                        </th>
                        <th class="p-1">
                            <button
                                @click="() => setSort('position')"
                                class="rounded flex items-center justify-start py-1 min-h-10 p-1 hover:bg-gray-200 w-full group"
                            >
                                <span class="font-bold leading-4"
                                    >Position</span
                                >
                                <i
                                    :class="
                                        state.order_by === 'position'
                                            ? state.sort === 'ASC'
                                                ? 'opacity-100'
                                                : 'opacity-100 rotate-180'
                                            : 'opacity-30'
                                    "
                                    class="ri-arrow-down-line text-lg font-thin transform"
                                ></i>
                            </button>
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
                        <td class="p-1 w-60px">
                            <label
                                class="flex items-center justify-center"
                                v-if="row.id !== 1"
                            >
                                <input
                                    type="checkbox"
                                    :checked="state.selected.includes(row.id)"
                                    @input="(e) => handleSelect(e, row.id)"
                                />
                            </label>
                        </td>
                        <td class="p-1">
                            <div
                                class="flex items-center justify-start p-1 h-35px"
                            >
                                {{ row.email }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div class="flex items-center justify-start p-1">
                                {{ row.name }}
                            </div>
                        </td>
                        <td class="p-1">
                            <div class="flex items-center justify-start p-1">
                                {{ row.position }}
                            </div>
                        </td>
                        <td class="p-1 w-60px">
                            <div class="flex items-center justify-start">
                                <router-link
                                    :to="'/desktop/users/edit/' + row.id"
                                    v-if="row.id !== 1"
                                >
                                    <div
                                        class="text-xl p-1 text-[#667085] hover:bg-gray-100 rounded"
                                    >
                                        <i class="ri-pencil-line"></i>
                                    </div>
                                </router-link>
                                <button
                                    v-if="row.id !== 1"
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
        content="Are you sure want to delete selected user?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedUser"
    >
    </Confirm>
</template>
