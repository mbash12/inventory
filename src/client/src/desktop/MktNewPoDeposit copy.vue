<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import {
    getProject,
    createPodeposit,
    updatePodeposit,
    currentUser,
    getClientList,
    nom,
    getPodeposit,
    goto,
} from "../services/service";
import Select1 from "../components/Select1.vue";

import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
const user = currentUser.user?.user;

const confirmDelete = ref(false);
const confirmDeleteGroup = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    selected: null,
    clients: [],
    errors: {},
    id: null,
    job_number: null,
    pic_name: null,
    client_po_date: null,
    closed_at: null,
    client_po_number: null,
    client_company: null,
    client_pic_name: null,
    budget: null,
    expense: null,
    balance: null,
    status: "open",
    products_group: [],
    selectedGroup: null,
    po_deposits: [],
});

const deleteSelectedProduct = () => {
    if (state.selected[0] == "deposit") {
        state.po_deposits[state.selected[1]].products.splice(
            state.selected[2],
            1
        );
    } else {
        state.products_group[state.selected[1]].products.splice(
            state.selected[2],
            1
        );
    }
    confirmDelete.value = false;
};
const deleteSelectedGroup = () => {
    if (state.selectedGroup[0] == "deposit") {
        let po = state.po_deposits[state.selectedGroup[1]].client_po_number;
        state.products_group = state.products_group.filter(
            (e) => e.client_po_number !== po
        );
        state.po_deposits.splice(state.selectedGroup[1], 1);
    } else {
        state.products_group.splice(state.selectedGroup[1], 1);
    }
    state.products_group = state.products_group.map((g) => {
    g.products = g.products.map((p) => {
        p.total_price = p.price != null && p.quantity != null ? p.price * p.quantity : null;
        return p;
    });
    g.total_price = g.products.reduce((e, c) => e + (c.total_price || 0), 0);
    return g;
});

state.po_deposits = state.po_deposits.map((g) => {
    g.products = g.products.map((p) => {
        p.total_price = p.price != null && p.quantity != null ? p.price * p.quantity : null;
        return p;
    });
    g.total_price = g.products.reduce((e, c) => e + (c.total_price || 0), 0);
    return g;
});
        
        state.balance = state.budget - state.expense ?? 0;
    state.expense = state.products_group.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    );
    state.budget = state.po_deposits.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    );
    state.balance = state.budget - state.expense ?? 0;

    confirmDeleteGroup.value = false;
};
const setDeleteGroup = (type, i) => {
    state.selectedGroup = [type, i];
    confirmDeleteGroup.value = true;
};
const setDelete = (type, i, ii) => {
    state.selected = [type, i, ii];
    confirmDelete.value = true;
};
const submit = () => {
    loading();
    let data = {
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        closed_at: state.closed_at,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_pic_name: state.client_pic_name,
        status: state.status,
        budget: state.budget ?? 0,
        expense: state.expense ?? 0,
        balance: state.balance ?? 0,
        products_group: state.products_group,
        po_deposits: state.po_deposits,
    };
    if (state.id === null) {
        createPodeposit(data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                state.errors = r.errors;
                alertShowFailed.value = true;
            }
        });
    } else {
        updatePodeposit(state.id, data).then((r) => {
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
const init = () => {
    loading();
    getClientList().then((r) => {
        if (r.code === 200) {
            state.clients = r.data;
        }
    });
    state.pic_name = currentUser.user.user.name;
    addNewDeposit();
    // addNewGroup();
    if (currentUrl.includes("edit")) {
        let id = currentUrl.split("/").slice(-1);
        getPodeposit(id).then((r) => {
            loading(false);
            if (r.code === 200) {
                let data = r.data;
                state.id = data.id;
                state.job_number = data.job_number;
                state.client_po_date = dayjs(data.client_po_date).format(
                    "YYYY-MM-DD"
                );
                state.pic_name = data.pic_name;
                state.client_po_number = data.client_po_number;
                state.client_company = data.client_company;
                state.client_pic_name = data.client_pic_name;
                state.status = data.status;
                if (data.closed_at) {
                    state.closed_at = dayjs(data.closed_at).format(
                        "YYYY-MM-DD"
                    );
                } else {
                    state.closed_at = null;
                }
                state.expense = data.expense;
                state.balance = data.balance;
                state.budget = data.budget;
                state.products_group = [
                    ...data.projects_data
                        .filter((e) => e.is_real)
                        .map((e) => ({
                            ...e,
                            products: e.products_data,
                        })),
                ];
                state.po_deposits = [
                    ...data.projects_data
                        .filter((e) => !e.is_real)
                        .map((e) => ({
                            ...e,
                            products: e.products_data,
                            client_po_date: dayjs(e.client_po_date).format(
                                "YYYY-MM-DD"
                            ),
                        })),
                ];

                setTimeout(() => {
                    if (
                        data.status == "close" ||
                        (user.position != "marketing" &&
                            user.position != "admin")
                    ) {
                        document
                            .querySelectorAll(
                                ".proform input, .proform textarea, .proform .input"
                            )
                            .forEach((el) => {
                                el.setAttribute("disabled", true);
                                el.classList.remove("bg-white");
                                el.classList.remove("bg-transparent");
                                el.classList.add("bg-gray-50");
                            });
                        document.querySelectorAll(".hidon").forEach((el) => {
                            el.style.display = "none";
                        });
                    }
                }, 500);
            }
        });
    } else {
        loading(false);
    }
};
onMounted(() => {
    init();
});
const sendToDel = (index) => {
    state.products_group[index].sent_to_del_at = dayjs().format("YYYY-MM-DD");
    submit();
};
const closePO = () => {
    state.closed_at = dayjs().format("YYYY-MM-DD HH:mm:ss");
    state.status = "close";
    submit();
};
const addNewGroup = (po_number) => {
    state.products_group.push({
        title: null,
        job_number: null,
        sent_to_del_at: null,
        total_price: null,
        client_po_number: po_number,
        products: [
            {
                name: null,
                quantity: null,
                description: null,
                price: null,
                total_price: null,
                is_production: true,
            },
        ],
    });
    state.products_group.map((g, i) => {
        g.job_number = state.job_number
            ? state.job_number + "-" + (i + 1).toString().padStart(3, "0")
            : null;
        return g;
    });
};
const addNewDeposit = () => {
    state.po_deposits.push({
        title: null,
        job_number: null,
        client_po_number: null,
        client_po_date: null,
        client_pic_name: null,
        total_price: null,
        sent_to_del_at: null,
        products: [
            {
                name: null,
                quantity: null,
                description: null,
                price: null,
                total_price: null,
            },
        ],
    });

    state.po_deposits.map((g, i) => {
        g.job_number =
            state.job_number + "-CD" + (i + 1).toString().padStart(3, "0");
        return g;
    });
};
// vue watch state.products_group
watch(
    () => state.products_group,
    (group) => {
        if (group.length) {
            group.map((g) => {
                g.products.map((p) => {
                    if (p.price != null && p.quantity != null) {
                        p.total_price = p.price * p.quantity;
                    } else {
                        p.total_price = null;
                    }
                    return p;
                });
                g.total_price = g.products
                    .filter((e) => e.total_price != null)
                    .reduce((e, c) => e + c.total_price, 0);
                return g;
            });
            state.expense = group
                .filter((e) => e.total_price != null)
                .reduce((e, c) => e + c.total_price, 0);
            state.balance = state.budget - state.expense ?? 0;
        }
    },
    { deep: true }
);
watch(
    () => state.po_deposits,
    (group) => {
        if (group.length) {
            group.forEach((g) => {
                g.products.forEach((p) => {
                    p.total_price = (p.price != null && p.quantity != null) ? p.price * p.quantity : null;
                });
                g.total_price = g.products
                    .reduce((acc, e) => acc + (e.total_price || 0), 0);
            });
            state.client_po_number = group[0].client_po_number;
            state.client_po_date = group[0].client_po_date;
            state.budget = group
                .reduce((acc, e) => acc + (e.total_price || 0), 0);
            state.balance = state.budget - (state.expense ?? 0);
        }
    },
    { deep: true }
);
watch(
    () => state.budget,
    (budget) => {
        state.balance = state.budget - state.expense ?? 0;
    }
);
watch(
    () => state.job_number,
    (job) => {
        state.products_group.map((g, i) => {
            g.job_number =
                state.job_number + "-" + (i + 1).toString().padStart(3, "0");
            return g;
        });
        state.po_deposits.map((g, i) => {
            g.job_number =
                state.job_number + "-CD" + (i + 1).toString().padStart(3, "0");
            return g;
        });
    }
);
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div
                    class="cursor-pointer text-2xl text-[#001737] font-semibold text-left"
                    @click="goto('/desktop/podeposits?filter=true')"
                >
                    <i class="ri-arrow-left-line"></i>
                </div>
                <span class="text-2xl text-[#001737] font-semibold text-left">
                    {{
                        currentUrl.includes("add")
                            ? "Add New PO Deposit"
                            : "Edit PO Deposit"
                    }}
                </span>
                <span
                    class="text-md text-green-700 font-semibold text-left py-1 px-3 bg-green-100 border border-green-500 rounded-2xl"
                    v-if="
                        currentUrl.includes('edit') && state.status != 'close'
                    "
                >
                    Open Order
                </span>
                <span
                    class="text-md text-red-700 font-semibold text-left py-1 px-3 bg-red-100 border border-red-500 rounded-2xl"
                    v-if="
                        currentUrl.includes('edit') && state.status == 'close'
                    "
                >
                    Closed
                </span>
            </div>

            <button
                class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm hidon"
                v-if="
                    currentUrl.includes('edit') &&
                    state.status != 'close' &&
                    state.products_group.every((e) => e.sent_to_del_at != null)
                "
                @click="closePO"
            >
                <strong>Close This PO</strong>
            </button>
        </div>
        <form
            @submit.prevent="submit"
            class="w-full bg-white rounded-lg shadow p-8 proform"
        >
            <div class="flex gap-8">
                <div class="w-1/2">
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Job Number</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                v-model="state.job_number"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-briefcase-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('job_number')"
                                >{{ state.errors?.job_number[0] }}</span
                            >
                        </div>
                    </label>
                    <!-- <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">PO Open Date</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="date"
                                class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                v-model="state.client_po_date"
                                onfocus="this.showPicker()"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
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
                    </label> -->
                    <!-- <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">PO Close Date</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="date"
                                class="bg-gray-50 w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                v-model="state.closed_at"
                                onfocus="this.showPicker()"
                                disabled
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-calendar-event-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('closed_at')"
                                >{{ state.errors?.closed_at[0] }}</span
                            >
                        </div>
                    </label> -->

                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left"
                            >Marketing PIC Name</span
                        >
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.pic_name"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('pic_name')"
                                >{{ state.errors?.pic_name[0] }}</span
                            >
                        </div>
                    </label>
                </div>
                <div class="w-1/2">
                    <!-- <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">PO Number</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.client_po_number"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-box-3-line"></i>
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
                                >{{ state.errors?.client_po_number[0] }}</span
                            >
                        </div>
                    </label> -->
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Client Company</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <Select1
                                @select="(e) => (state.client_company = e)"
                                :value="state.client_company"
                                required="true"
                            />

                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-hotel-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="
                                    state.errors.hasOwnProperty(
                                        'client_company'
                                    )
                                "
                                >{{ state.errors?.client_company[0] }}</span
                            >
                        </div>
                    </label>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Client PIC Name</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.client_pic_name"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="
                                    state.errors.hasOwnProperty(
                                        'client_pic_name'
                                    )
                                "
                                >{{ state.errors?.client_pic_name[0] }}</span
                            >
                        </div>
                    </label>

                    <!-- <label class="flex flex-col gap-1 mb-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-left">Budget</span>
                            <span class="text-xs text-gray-400 text-left">{{
                                nom(state.budget ?? 0)
                            }}</span>
                        </div>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="number"
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.budget"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-money-dollar-box-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('budget')"
                                >{{ state.errors?.budget[0] }}</span
                            >
                        </div>
                    </label> -->
                </div>
            </div>
            <div>
                <div class="flex justify-between my-2 items-center">
                    <div class="text-left flex flex-col">
                        <span class="text-lg"> Deposit </span>
                        <!-- <span class="text-sm font-normal text-gray-500 italic">
                            (Grouped by delivery)
                        </span> -->
                    </div>

                    <button
                        type="button"
                        class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm ml-auto hidon"
                        @click="addNewDeposit"
                        v-if="state.status != 'close'"
                    >
                        <i class="ri-play-list-add-line text-xl"></i>
                        <strong>Add New Deposit</strong>
                    </button>
                </div>
                <div class="h-4"></div>
                <template v-for="(group, i) in state.po_deposits" :key="i">
                    <div class="border-b-2 border-red-500 pb-6 mb-6">
                        <div class="text-left flex items-center">
                            <span class="text-lg"> Non Actual </span>
                        </div>
                        <div class="mb-6">
                            <div class="border rounded-lg">
                                <table
                                    class="w-full rounded-lg overflow-hidden"
                                >
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <td
                                                colspan="7"
                                                class="bg-gray-50 border-b-2 pt-2"
                                            >
                                                <table
                                                    class="px-3 text-14px w-full"
                                                >
                                                    <thead>
                                                        <tr>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                PO Number
                                                            </th>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                PIC
                                                            </th>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                PO Date
                                                            </th>
                                                            <td colspan="2">
                                                                <button
                                                                    type="button"
                                                                    v-if="
                                                                        state.status ==
                                                                            'open' &&
                                                                        user.position !==
                                                                            'delivery' &&
                                                                        user.position !==
                                                                            'finance'
                                                                    "
                                                                    class="text-xl px-2 h-35px text-red-500 hover:bg-gray-100 rounded"
                                                                    title="Delete non actual group"
                                                                    @click="
                                                                        setDeleteGroup(
                                                                            'deposit',
                                                                            i
                                                                        )
                                                                    "
                                                                >
                                                                    <i
                                                                        class="ri-delete-bin-line"
                                                                    ></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="p-1">
                                                                <input
                                                                    type="text"
                                                                    class="w-full h-30px border rounded px-2"
                                                                    v-model="
                                                                        group.client_po_number
                                                                    "
                                                                    required
                                                                    :disabled="
                                                                        state.products_group.filter(
                                                                            (
                                                                                e
                                                                            ) =>
                                                                                e.client_po_number ==
                                                                                group.client_po_number
                                                                        )
                                                                            .length >
                                                                        0
                                                                    "
                                                                />
                                                            </td>
                                                            <td class="p-1">
                                                                <input
                                                                    type="text"
                                                                    class="w-full h-30px border rounded px-2"
                                                                    v-model="
                                                                        group.client_pic_name
                                                                    "
                                                                    required
                                                                />
                                                            </td>
                                                            <td class="p-1">
                                                                <input
                                                                    type="date"
                                                                    class="w-full h-30px border rounded px-2"
                                                                    v-model="
                                                                        group.client_po_date
                                                                    "
                                                                    required
                                                                />
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <table
                                                    class="px-3 text-14px w-full"
                                                    v-if="
                                                        user.position !==
                                                        'delivery'
                                                    "
                                                >
                                                    <thead>
                                                        <tr>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                Subtotal Budget
                                                            </th>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                Used Budget
                                                            </th>
                                                            <th
                                                                class="p-1 w-1/3 font-medium text-left"
                                                            >
                                                                Remaining Budget
                                                            </th>
                                                            <td colspan="2">
                                                                <div
                                                                    class="w-36px"
                                                                ></div>
                                                            </td>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="p-1">
                                                                <div
                                                                    class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                                >
                                                                    <span
                                                                        class="text-14px text-gray-500 font-semibold"
                                                                        >{{
                                                                            group.total_price
                                                                                ? nom(
                                                                                      group.total_price
                                                                                  )
                                                                                : 0
                                                                        }}</span
                                                                    >
                                                                </div>
                                                            </td>
                                                            <td class="p-1">
                                                                <div
                                                                    class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                                >
                                                                    <span
                                                                        class="text-14px text-gray-500 font-semibold"
                                                                        >{{
                                                                            nom(
                                                                                state.products_group
                                                                                    .filter(
                                                                                        (
                                                                                            e
                                                                                        ) =>
                                                                                            e.client_po_number ==
                                                                                            group.client_po_number
                                                                                    )
                                                                                    .reduce(
                                                                                        (
                                                                                            a,
                                                                                            b
                                                                                        ) =>
                                                                                            a +
                                                                                            b.total_price,
                                                                                        0
                                                                                    ) ??
                                                                                    0
                                                                            )
                                                                        }}</span
                                                                    >
                                                                </div>
                                                            </td>
                                                            <td class="p-1">
                                                                <div
                                                                    class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                                >
                                                                    <span
                                                                        class="text-14px text-gray-500 font-semibold"
                                                                        >{{
                                                                            nom(
                                                                                group.total_price -
                                                                                    state.products_group
                                                                                        .filter(
                                                                                            (
                                                                                                e
                                                                                            ) =>
                                                                                                e.client_po_number ==
                                                                                                group.client_po_number
                                                                                        )
                                                                                        .reduce(
                                                                                            (
                                                                                                a,
                                                                                                b
                                                                                            ) =>
                                                                                                a +
                                                                                                b.total_price,
                                                                                            0
                                                                                        ) ??
                                                                                    group.total_price
                                                                            )
                                                                        }}</span
                                                                    >
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr class="border-b">
                                            <th
                                                class="text-left text-sm px-1 py-2 w-1/5"
                                            >
                                                Name
                                            </th>
                                            <th
                                                class="text-left text-sm px-1 py-2 w-80px"
                                            >
                                                Quantity
                                            </th>
                                            <th
                                                class="text-left text-sm px-1 py-2 w-120px"
                                            >
                                                Price/Pcs
                                            </th>
                                            <th
                                                class="text-left text-sm px-1 py-2 w-150px"
                                            >
                                                Total Amount
                                            </th>
                                            <th
                                                class="text-left text-sm px-1 py-2"
                                            >
                                                Description
                                            </th>
                                            <th class="w-60px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            class="border-b"
                                            v-for="(
                                                product, ii
                                            ) in group.products"
                                            :key="ii"
                                        >
                                            <td class="">
                                                <div
                                                    class="flex items-center flex-col gap-1"
                                                >
                                                    <input
                                                        type="text"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="Product Name"
                                                        v-model="product.name"
                                                        required
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                    />
                                                    <span
                                                        class="text-xs text-red-500 text-left w-full block pl-4"
                                                        v-if="
                                                            state.errors.hasOwnProperty(
                                                                `products.${ii}.name`
                                                            )
                                                        "
                                                        >{{
                                                            state.errors[
                                                                `products.${ii}.name`
                                                            ][0].replace(
                                                                `products.${ii}.name `,
                                                                ""
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="">
                                                <div
                                                    class="flex items-center flex-col gap-1"
                                                >
                                                    <input
                                                        type="number"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="0"
                                                        v-model="
                                                            product.quantity
                                                        "
                                                        required
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                    />
                                                    <span
                                                        class="text-xs text-red-500 text-left w-full block pl-4"
                                                        v-if="
                                                            state.errors.hasOwnProperty(
                                                                `products.${ii}.quantity`
                                                            )
                                                        "
                                                        >{{
                                                            state.errors[
                                                                `products.${ii}.quantity`
                                                            ][0].replace(
                                                                `products.${ii}.quantity `,
                                                                ""
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="">
                                                <div
                                                    class="flex items-center flex-col gap-1"
                                                >
                                                    <input
                                                        type="number"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="0"
                                                        v-model="product.price"
                                                        required
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                        v-if="
                                                            user.position !==
                                                            'delivery'
                                                        "
                                                    />
                                                    <input
                                                        type="number"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="0"
                                                        required
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                        v-else
                                                    />
                                                    <span
                                                        class="text-xs text-red-500 text-left w-full block pl-4"
                                                        v-if="
                                                            state.errors.hasOwnProperty(
                                                                `products.${ii}.price`
                                                            )
                                                        "
                                                        >{{
                                                            state.errors[
                                                                `products.${ii}.price`
                                                            ][0].replace(
                                                                `products.${ii}.price `,
                                                                ""
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </td>

                                            <td class="">
                                                <div
                                                    class="flex items-center flex-col gap-1"
                                                >
                                                    <input
                                                        type="text"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="0"
                                                        disabled
                                                        :value="
                                                            product.total_price
                                                                ? nom(
                                                                      product.total_price
                                                                  )
                                                                : null
                                                        "
                                                        v-if="
                                                            user.position !==
                                                            'delivery'
                                                        "
                                                    />
                                                    <input
                                                        type="number"
                                                        class="w-full h-40px px-1 text-sm bg-transparent"
                                                        placeholder="0"
                                                        required
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                        v-else
                                                    />
                                                    <span
                                                        class="text-xs text-red-500 text-left w-full block pl-4"
                                                        v-if="
                                                            state.errors.hasOwnProperty(
                                                                `products.${ii}.total_price`
                                                            )
                                                        "
                                                        >{{
                                                            state.errors[
                                                                `products.${ii}.total_price`
                                                            ][0].replace(
                                                                `products.${ii}.total_price `,
                                                                ""
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="">
                                                <div
                                                    class="flex items-center flex-col gap-1"
                                                >
                                                    <textarea
                                                        class="w-full h-full px-1 text-sm h-40px pt-10px bg-transparent"
                                                        placeholder="Product Description"
                                                        v-model="
                                                            product.description
                                                        "
                                                        :disabled="
                                                            group.manufacture !=
                                                            null
                                                        "
                                                    ></textarea>

                                                    <span
                                                        class="text-xs text-red-500 text-left w-full block pl-4"
                                                        v-if="
                                                            state.errors.hasOwnProperty(
                                                                `products.${ii}.description`
                                                            )
                                                        "
                                                        >{{
                                                            state.errors[
                                                                `products.${ii}.description`
                                                            ][0].replace(
                                                                `products.${ii}.description `,
                                                                ""
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="p-1 input">
                                                <button
                                                    type="button"
                                                    v-if="
                                                        group.products.length >
                                                            1 &&
                                                        state.status ==
                                                            'open' &&
                                                        user.position !==
                                                            'delivery' &&
                                                        user.position !==
                                                            'finance'
                                                    "
                                                    class="text-xl px-2 h-35px text-[#667085] hover:bg-gray-100 rounded"
                                                    @click="
                                                        setDelete(
                                                            'deposit',
                                                            i,
                                                            ii
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="ri-delete-bin-line"
                                                    ></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr v-if="state.status == 'open'">
                                            <td colspan="8">
                                                <div class="flex border-t">
                                                    <div class="flex-1">
                                                        <button
                                                            @click="
                                                                addNewGroup(
                                                                    group.client_po_number
                                                                )
                                                            "
                                                            v-if="
                                                                state.status !=
                                                                    'close' &&
                                                                user.position !==
                                                                    'delivery' &&
                                                                user.position !==
                                                                    'finance' &&
                                                                group.client_po_number
                                                            "
                                                            type="button"
                                                            class="flex-1 text-sm px-4 py-2 bg-red-500 text-white hover:bg-red-600 items-center justify-center flex gap-4 w-full rounded-bl-lg"
                                                        >
                                                            <i
                                                                class="ri-add-box-line text-xl"
                                                            ></i>
                                                            <strong
                                                                >Add New
                                                                Actual</strong
                                                            >
                                                        </button>
                                                    </div>
                                                    <div class="flex-1"></div>
                                                    <div class="flex-1"></div>
                                                    <button
                                                        type="button"
                                                        class="flex-1 text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-br-lg hidon"
                                                        @click="
                                                            () =>
                                                                group.products.push(
                                                                    {
                                                                        name: '',
                                                                        quantity:
                                                                            '',
                                                                        description:
                                                                            '',
                                                                        price: null,
                                                                        total_price:
                                                                            null,
                                                                        is_production: true,
                                                                    }
                                                                )
                                                        "
                                                    >
                                                        <i
                                                            class="ri-add-box-line text-xl"
                                                        ></i>
                                                        <span>
                                                            Add More Item
                                                        </span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-between items-center">
                            <div class="text-left flex items-center">
                                <span class="text-lg"> Actual </span>
                            </div>
                        </div>
                        <template
                            v-for="(group1, i) in state.products_group.filter(
                                (e) =>
                                    e.client_po_number == group.client_po_number
                            )"
                            :key="i"
                        >
                            <div class="mb-6">
                                <div class="border rounded-lg">
                                    <table
                                        class="w-full rounded-lg overflow-hidden"
                                    >
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <td
                                                    colspan="7"
                                                    class="bg-gray-50 border-b-2 pt-2"
                                                >
                                                    <table
                                                        class="px-3 text-14px w-full"
                                                    >
                                                        <thead>
                                                            <tr>
                                                                <th
                                                                    class="p-1 w-1/4 font-medium text-left"
                                                                >
                                                                    Title
                                                                </th>
                                                                <th
                                                                    class="p-1 w-1/4 font-medium text-left"
                                                                >
                                                                    Delivery Job
                                                                    Number
                                                                </th>
                                                                <th
                                                                    class="p-1 w-1/4 font-medium text-left"
                                                                >
                                                                    Sent To
                                                                    Delivery
                                                                </th>
                                                                <th
                                                                    class="p-1 w-1/4 font-medium text-left"
                                                                >
                                                                    Subtotal
                                                                </th>
                                                                <td colspan="2">
                                                                    <button
                                                                        type="button"
                                                                        v-if="
                                                                            state.status ==
                                                                                'open' &&
                                                                            user.position !==
                                                                                'delivery' &&
                                                                            user.position !==
                                                                                'finance'
                                                                        "
                                                                        class="text-xl px-2 h-35px text-red-500 hover:bg-gray-100 rounded"
                                                                        title="Delete actual group"
                                                                        @click="
                                                                            setDeleteGroup(
                                                                                'product',
                                                                                state.products_group.findIndex(
                                                                                    (
                                                                                        e
                                                                                    ) =>
                                                                                        e.job_number ==
                                                                                        group1.job_number
                                                                                )
                                                                            )
                                                                        "
                                                                    >
                                                                        <i
                                                                            class="ri-delete-bin-line"
                                                                        ></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td class="p-1">
                                                                    <input
                                                                        type="text"
                                                                        class="w-full h-30px border rounded px-2"
                                                                        v-model="
                                                                            group1.title
                                                                        "
                                                                        :class="
                                                                            group1.manufacture ==
                                                                            null
                                                                                ? 'bg-white'
                                                                                : 'bg-gray-50'
                                                                        "
                                                                        :disabled="
                                                                            group1.manufacture !=
                                                                            null
                                                                        "
                                                                    />
                                                                </td>
                                                                <td class="p-1">
                                                                    <input
                                                                        type="text"
                                                                        class="w-full h-30px border rounded px-2"
                                                                        v-model="
                                                                            group1.job_number
                                                                        "
                                                                        :class="
                                                                            group1.manufacture ==
                                                                            null
                                                                                ? 'bg-white'
                                                                                : 'bg-gray-50'
                                                                        "
                                                                        :disabled="
                                                                            group1.manufacture !=
                                                                            null
                                                                        "
                                                                    />
                                                                </td>
                                                                <td class="p-1">
                                                                    <div
                                                                        class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                                    >
                                                                        <span
                                                                            class="text-14px text-gray-500 font-semibold"
                                                                            >{{
                                                                                group1.sent_to_del_at
                                                                            }}</span
                                                                        >
                                                                    </div>
                                                                </td>
                                                                <td class="p-1">
                                                                    <div
                                                                        class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                                    >
                                                                        <span
                                                                            class="text-14px text-gray-500 font-semibold"
                                                                            >{{
                                                                                group1.total_price
                                                                                    ? nom(
                                                                                          group1.total_price
                                                                                      )
                                                                                    : 0
                                                                            }}</span
                                                                        >
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr class="border-b">
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-1/5"
                                                >
                                                    Name
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-80px"
                                                >
                                                    Quantity
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-120px"
                                                >
                                                    Price/Pcs
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-150px"
                                                >
                                                    Total Amount
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2"
                                                >
                                                    Description
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-80px"
                                                >
                                                    Production
                                                </th>
                                                <th class="w-60px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                class="border-b"
                                                v-for="(
                                                    product, ii
                                                ) in group1.products"
                                                :key="ii"
                                            >
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <input
                                                            type="text"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="Product Name"
                                                            v-model="
                                                                product.name
                                                            "
                                                            required
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `products.${ii}.name`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `products.${ii}.name`
                                                                ][0].replace(
                                                                    `products.${ii}.name `,
                                                                    ""
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>

                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <input
                                                            type="number"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            v-model="
                                                                product.quantity
                                                            "
                                                            required
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `products.${ii}.quantity`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `products.${ii}.quantity`
                                                                ][0].replace(
                                                                    `products.${ii}.quantity `,
                                                                    ""
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <input
                                                            type="number"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            v-model="
                                                                product.price
                                                            "
                                                            required
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                            v-if="
                                                                user.position !==
                                                                'delivery'
                                                            "
                                                        />
                                                        <input
                                                            type="number"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            required
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                            v-else
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `products.${ii}.price`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `products.${ii}.price`
                                                                ][0].replace(
                                                                    `products.${ii}.price `,
                                                                    ""
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <input
                                                            type="text"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            disabled
                                                            :value="
                                                                product.total_price
                                                                    ? nom(
                                                                          product.total_price
                                                                      )
                                                                    : null
                                                            "
                                                            v-if="
                                                                user.position !==
                                                                'delivery'
                                                            "
                                                        />
                                                        <input
                                                            type="number"
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            required
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                            v-else
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `products.${ii}.total_price`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `products.${ii}.total_price`
                                                                ][0].replace(
                                                                    `products.${ii}.total_price `,
                                                                    ""
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <textarea
                                                            class="w-full h-full px-1 text-sm h-40px pt-10px bg-transparent"
                                                            placeholder="Product Description"
                                                            v-model="
                                                                product.description
                                                            "
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                        ></textarea>

                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `products.${ii}.description`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `products.${ii}.description`
                                                                ][0].replace(
                                                                    `products.${ii}.description `,
                                                                    ""
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="p-1 input">
                                                    <div
                                                        class="px-1 flex justify-center w-full"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            value="production"
                                                            v-model="
                                                                product.is_production
                                                            "
                                                            :disabled="
                                                                group1.manufacture !=
                                                                null
                                                            "
                                                        />
                                                    </div>
                                                </td>
                                                <td class="p-1 input">
                                                    <button
                                                        type="button"
                                                        v-if="
                                                            group1.products
                                                                .length > 1 &&
                                                            group1.manufacture ==
                                                                null &&
                                                            state.status ==
                                                                'open' &&
                                                            user.position !==
                                                                'delivery' &&
                                                            user.position !==
                                                                'finance'
                                                        "
                                                        class="text-xl px-2 h-35px text-[#667085] hover:bg-gray-100 rounded"
                                                        @click="
                                                            setDelete(
                                                                'product',
                                                                state.products_group.findIndex(
                                                                    (e) =>
                                                                        e.job_number ==
                                                                        group1.job_number
                                                                ),
                                                                ii
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="ri-delete-bin-line"
                                                        ></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr
                                                v-if="
                                                    group1.sent_to_del_at ==
                                                        null &&
                                                    user.position !==
                                                        'delivery' &&
                                                    user.position !== 'finance'
                                                "
                                            >
                                                <td colspan="8">
                                                    <div class="flex border-t">
                                                        <button
                                                            @click="
                                                                () =>
                                                                    sendToDel(i)
                                                            "
                                                            type="button"
                                                            class="flex-1 text-sm px-4 py-2 bg-red-500 text-white hover:bg-red-600 items-center justify-center flex gap-4 w-full rounded-bl-lg"
                                                            v-if="group1.id"
                                                        >
                                                            <i
                                                                class="ri-share-forward-2-line text-xl"
                                                            ></i>
                                                            <strong
                                                                >Send to
                                                                Delivery</strong
                                                            >
                                                        </button>
                                                        <div
                                                            class="flex-1"
                                                            v-else
                                                        ></div>
                                                        <div
                                                            class="flex-1"
                                                        ></div>
                                                        <div
                                                            class="flex-1"
                                                        ></div>
                                                        <button
                                                            type="button"
                                                            class="flex-1 text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-br-lg hidon"
                                                            @click="
                                                                () =>
                                                                    group1.products.push(
                                                                        {
                                                                            name: '',
                                                                            quantity:
                                                                                '',
                                                                            description:
                                                                                '',
                                                                            price: null,
                                                                            total_price:
                                                                                null,
                                                                            is_production: true,
                                                                        }
                                                                    )
                                                            "
                                                        >
                                                            <i
                                                                class="ri-add-box-line text-xl"
                                                            ></i>
                                                            <span>
                                                                Add More Item
                                                            </span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <!-- <div class="flex justify-between my-2 items-center">
                    <div class="text-left flex flex-col">
                        <span class="text-lg"> Actual </span>
                    </div>

                    <button
                        type="button"
                        class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm ml-auto hidon"
                        @click="addNewGroup"
                        v-if="
                             state.status != 'close'
                        "
                    >
                        <i class="ri-play-list-add-line text-xl"></i>
                        <strong>Add New Group</strong>
                    </button>
                </div>
                <div class="h-4"></div>
                <template v-for="(group, i) in state.products_group" :key="i">
                    <div class="mb-8">
                        <div class="border rounded-lg">
                            <table class="w-full rounded-lg overflow-hidden">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <td
                                            colspan="7"
                                            class="bg-gray-50 border-b-2 pt-2"
                                        >
                                            <table
                                                class="px-3 text-14px w-full"
                                            >
                                                <thead>
                                                    <tr>
                                                        <th
                                                            class="p-1 w-1/4 font-medium text-left"
                                                        >
                                                            Title
                                                        </th>
                                                        <th
                                                            class="p-1 w-1/4 font-medium text-left"
                                                        >
                                                            Delivery Job Number
                                                        </th>
                                                        <th
                                                            class="p-1 w-1/4 font-medium text-left"
                                                        >
                                                            Sent To Delivery
                                                        </th>
                                                        <th
                                                            class="p-1 w-1/4 font-medium text-left"
                                                        >
                                                            Subtotal
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="p-1">
                                                            <input
                                                                type="text"
                                                                class="w-full h-30px border rounded px-2"
                                                                v-model="
                                                                    group.title
                                                                "
                                                                :class="
                                                                    group.manufacture ==
                                                                    null
                                                                        ? 'bg-white'
                                                                        : 'bg-gray-50'
                                                                "
                                                                :disabled="
                                                                    group.manufacture !=
                                                                    null
                                                                "
                                                            />
                                                        </td>
                                                        <td class="p-1">
                                                            <div
                                                                class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                            >
                                                                <span
                                                                    class="text-14px text-gray-500 font-semibold"
                                                                    >{{
                                                                        group.job_number
                                                                    }}</span
                                                                >
                                                            </div>
                                                        </td>
                                                        <td class="p-1">
                                                            <div
                                                                class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                            >
                                                                <span
                                                                    class="text-14px text-gray-500 font-semibold"
                                                                    >{{
                                                                        group.sent_to_del_at
                                                                    }}</span
                                                                >
                                                            </div>
                                                        </td>
                                                        <td class="p-1">
                                                            <div
                                                                class="w-full h-30px bg-gray-50 border rounded px-2 flex items-center"
                                                            >
                                                                <span
                                                                    class="text-14px text-gray-500 font-semibold"
                                                                    >{{
                                                                        group.total_price
                                                                            ? nom(
                                                                                  group.total_price
                                                                              )
                                                                            : 0
                                                                    }}</span
                                                                >
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr class="border-b">
                                        <th
                                            class="text-left text-sm px-1 py-2 w-1/5"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="text-left text-sm px-1 py-2 w-80px"
                                        >
                                            Quantity
                                        </th>
                                        <th
                                            class="text-left text-sm px-1 py-2 w-120px"
                                        >
                                            Price/Pcs
                                        </th>
                                        <th
                                            class="text-left text-sm px-1 py-2 w-150px"
                                        >
                                            Total Amount
                                        </th>
                                        <th class="text-left text-sm px-1 py-2">
                                            Description
                                        </th>
                                        <th
                                            class="text-left text-sm px-1 py-2 w-80px"
                                        >
                                            Production
                                        </th>
                                        <th class="w-60px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        class="border-b"
                                        v-for="(product, ii) in group.products"
                                        :key="ii"
                                    >
                                        <td class="">
                                            <div
                                                class="flex items-center flex-col gap-1"
                                            >
                                                <input
                                                    type="text"
                                                    class="w-full h-40px px-1 text-sm bg-transparent"
                                                    placeholder="Product Name"
                                                    v-model="product.name"
                                                    required
                                                    :disabled="
                                                        group.manufacture !=
                                                        null
                                                    "
                                                />
                                                <span
                                                    class="text-xs text-red-500 text-left w-full block pl-4"
                                                    v-if="
                                                        state.errors.hasOwnProperty(
                                                            `products.${ii}.name`
                                                        )
                                                    "
                                                    >{{
                                                        state.errors[
                                                            `products.${ii}.name`
                                                        ][0].replace(
                                                            `products.${ii}.name `,
                                                            ""
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        
                                        <td class="">
                                            <div
                                                class="flex items-center flex-col gap-1"
                                            >
                                                <input
                                                    type="number"
                                                    class="w-full h-40px px-1 text-sm bg-transparent"
                                                    placeholder="0"
                                                    v-model="product.quantity"
                                                    required
                                                    :disabled="
                                                        group.manufacture !=
                                                        null
                                                    "
                                                />
                                                <span
                                                    class="text-xs text-red-500 text-left w-full block pl-4"
                                                    v-if="
                                                        state.errors.hasOwnProperty(
                                                            `products.${ii}.quantity`
                                                        )
                                                    "
                                                    >{{
                                                        state.errors[
                                                            `products.${ii}.quantity`
                                                        ][0].replace(
                                                            `products.${ii}.quantity `,
                                                            ""
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="">
                                            <div
                                                class="flex items-center flex-col gap-1"
                                            >
                                                <input
                                                    type="number"
                                                    class="w-full h-40px px-1 text-sm bg-transparent"
                                                    placeholder="0"
                                                    v-model="product.price"
                                                    required
                                                    :disabled="
                                                        group.manufacture !=
                                                        null
                                                    "
                                                />
                                                <span
                                                    class="text-xs text-red-500 text-left w-full block pl-4"
                                                    v-if="
                                                        state.errors.hasOwnProperty(
                                                            `products.${ii}.price`
                                                        )
                                                    "
                                                    >{{
                                                        state.errors[
                                                            `products.${ii}.price`
                                                        ][0].replace(
                                                            `products.${ii}.price `,
                                                            ""
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="">
                                            <div
                                                class="flex items-center flex-col gap-1"
                                            >
                                                <input
                                                    type="text"
                                                    class="w-full h-40px px-1 text-sm bg-transparent"
                                                    placeholder="0"
                                                    disabled
                                                    :value="
                                                        product.total_price
                                                            ? nom(
                                                                  product.total_price
                                                              )
                                                            : null
                                                    "
                                                />
                                                <span
                                                    class="text-xs text-red-500 text-left w-full block pl-4"
                                                    v-if="
                                                        state.errors.hasOwnProperty(
                                                            `products.${ii}.total_price`
                                                        )
                                                    "
                                                    >{{
                                                        state.errors[
                                                            `products.${ii}.total_price`
                                                        ][0].replace(
                                                            `products.${ii}.total_price `,
                                                            ""
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="">
                                            <div
                                                class="flex items-center flex-col gap-1"
                                            >
                                                <textarea
                                                    class="w-full h-full px-1 text-sm h-40px pt-10px bg-transparent"
                                                    placeholder="Product Description"
                                                    v-model="
                                                        product.description
                                                    "
                                                    :disabled="
                                                        group.manufacture !=
                                                        null
                                                    "
                                                ></textarea>

                                                <span
                                                    class="text-xs text-red-500 text-left w-full block pl-4"
                                                    v-if="
                                                        state.errors.hasOwnProperty(
                                                            `products.${ii}.description`
                                                        )
                                                    "
                                                    >{{
                                                        state.errors[
                                                            `products.${ii}.description`
                                                        ][0].replace(
                                                            `products.${ii}.description `,
                                                            ""
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="p-1 input">
                                            <div
                                                class="px-1 flex justify-center w-full"
                                            >
                                                <input
                                                    type="checkbox"
                                                    value="production"
                                                    v-model="
                                                        product.is_production
                                                    "
                                                    :disabled="
                                                        group.manufacture !=
                                                        null
                                                    "
                                                />
                                            </div>
                                        </td>
                                        <td class="p-1 input">
                                            <button
                                                type="button"
                                                v-if="
                                                    group.products.length > 1 &&
                                                    group.manufacture == null && state.status == 'open'
                                                    && user.position !== 'delivery'
                                                        && user.position !== 'finance'
                                                "
                                                class="text-xl px-2 h-35px text-[#667085] hover:bg-gray-100 rounded"
                                                @click="
                                                    setDelete('product', i, ii)
                                                "
                                            >
                                                <i
                                                    class="ri-delete-bin-line"
                                                ></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr v-if="group.sent_to_del_at == null">
                                        <td colspan="8">
                                            <div class="flex border-t">
                                                <button
                                                    @click="() => sendToDel(i)"
                                                    type="button"
                                                    class="flex-1 text-sm px-4 py-2 bg-red-500 text-white hover:bg-red-600 items-center justify-center flex gap-4 w-full rounded-bl-lg"
                                                    v-if="group.id"
                                                >
                                                    <i
                                                        class="ri-share-forward-2-line text-xl"
                                                    ></i>
                                                    <strong
                                                        >Send to
                                                        Delivery</strong
                                                    >
                                                </button>
                                                <div class="flex-1" v-else></div>
                                                <div class="flex-1"></div>
                                                <div class="flex-1"></div>
                                                <button
                                                    type="button"
                                                    class="flex-1 text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-br-lg hidon"
                                                    @click="
                                                        () =>
                                                            group.products.push(
                                                                {
                                                                    name: '',
                                                                    quantity:
                                                                        '',
                                                                    description:
                                                                        '',
                                                                    price: null,
                                                                    total_price:
                                                                        null,
                                                                    is_production: true,
                                                                }
                                                            )
                                                    "
                                                >
                                                    <i
                                                        class="ri-add-box-line text-xl"
                                                    ></i>
                                                    <span> Add More Item </span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </template> -->
                <div class="h-4"></div>
                <table class="ml-auto" v-if="user.position !== 'delivery'">
                    <tr class="my-2">
                        <td class="pr-8 font-bold text-[#667085] text-right">
                            Total :
                        </td>
                        <td class="pl-2 font-bold text-[#000000] text-right">
                            Rp. {{ nom(state.expense ?? 0) }}
                        </td>
                    </tr>
                    <tr class="my-2">
                        <td class="pr-8 font-bold text-[#667085] text-right">
                            Budget :
                        </td>
                        <td class="pl-2 font-bold text-[#000000] text-right">
                            Rp. {{ nom(state.budget ?? 0) }}
                        </td>
                    </tr>
                    <tr class="my-2">
                        <td class="pr-8 font-bold text-[#667085] text-right">
                            Remaining Budget :
                        </td>
                        <td class="pl-2 font-bold text-[#000000] text-right">
                            Rp. {{ nom(state.balance ?? 0) }}
                        </td>
                    </tr>
                </table>
                <div class="flex justify-end gap-4 mt-8">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        type="button"
                        @click="router.push('/desktop/podeposits?filter=true')"
                    >
                        <i class="ri-close-circle-line text-xl"></i>
                        <strong>Cancel</strong>
                    </button>
                    <button
                        class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center hidon"
                        v-if="state.status != 'close'"
                    >
                        <i class="ri-save-line text-xl"></i>
                        <strong>{{
                            currentUrl.includes("add") ? "Save" : "Update"
                        }}</strong>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected product?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedProduct"
    >
    </Confirm>
    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected group?"
        buttonText="Delete"
        :show="confirmDeleteGroup != false"
        @hide="confirmDeleteGroup = false"
        @fire="deleteSelectedGroup"
    >
    </Confirm>
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
        title="PO Deposit Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                router.push('/desktop/podeposits');
            }
        "
    ></Alert>
</template>
