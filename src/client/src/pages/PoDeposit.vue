<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    getPodeposit,
    updatePodeposit,
    createPodeposit,
    nom,
    getUomList,
    getTaxList
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Navbar from "../components/Navbar.vue";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
import ItemModal2 from "../components/ItemModal2.vue";
import ItemModal3 from "../components/ItemModal3.vue";
import ItemModal4 from "../components/ItemModal4.vue";
import Select2 from "../components/Select2.vue";
const confirmDelete = ref(false);
const confirmDeleteGroup = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const user = currentUser.user?.user;
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    clients: [],
    errors: {},
    id: null,
    job_number: null,
    pic_name: null,
    client_po_date: null,
    closed_at: null,
    client_po_number: null,
    client_company: null,
    client_code: null,
    client_pic_name: null,
    budget: null,
    expense: null,
    balance: null,
    status: "open",
    products_group: [],
    po_deposits: [],
    selectedgroup: null,
    selecteditems: null,
    selectedgroupitem: null,
    selected: null,
    selected_po: null,
    selected_group: null,
    edit_open: false,
    edit_open1: false,
    edit_open2: false,
    selected_deposit: null,
    selectedGroup: null,
});

// Reactive variables for UOM and Tax options
const uoms = ref([]);
const taxes = ref([]);
const handleDeleteGroup = (type) => {
    let i = type == "project" ? state.selected_group : state.selected_po;
    if (type == "deposit") {
        let po = state.po_deposits[i].client_po_number;
        state.products_group = state.products_group.filter(
            (e) => e.client_po_number !== po
        );
        state.po_deposits.splice(i, 1);
    } else {
        state.products_group.splice(i, 1);
    }
    state.products_group = state.products_group.map((g) => {
        g.products = g.products.map((p) => {
            p.total_price =
                p.price != null && p.quantity != null
                    ? p.price * p.quantity
                    : null;
            return p;
        });
        g.total_price = g.products.reduce(
            (total, p) => total + (p.total_price || 0),
            0 // Providing an initial value to avoid the error
        );
        return g;
    });

    state.balance = state.budget - state.expense ?? 0;

    state.po_deposits = state.po_deposits.map((g) => {
        g.products = g.products.map((p) => {
            p.total_price =
                p.price != null && p.quantity != null
                    ? p.price * p.quantity
                    : null;
            return p;
        });
        g.total_price = g.products.reduce(
            (total, p) => total + (p.total_price || 0),
            0
        );
        return g;
    });
    state.expense = state.products_group.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    );
    state.budget = state.po_deposits.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    );
    state.balance = state.budget - state.expense ?? 0;

    state.selected = null;
    state.selectedgroup = null;
    state.selecteditems = null;
    state.selectedgroupitem = null;
    state.edit_open1 = false;
    state.edit_open2 = false;
};

const handleDeposit = (i = null) => {
    state.edit_open1 = true;
    if (i !== null) {
        state.selected_po = i;
    }
};
const handleGroup = (i = null) => {
    state.edit_open2 = true;
    if (i !== null) {
        state.selected_group = i;
    }
};
const handleSelectedd = (deposit) => {
    state.edit_open2 = true;
    state.selected_deposit = deposit;
};
const handleSelected = (group, i = null, ii = null) => {
    state.edit_open = true;
    state.selectedgroup = group;
    if (i !== null) {
        state.selectedgroupitem = i;
        state.selecteditems = state[group][i].products;
        if (ii !== null) {
            state.selected = ii;
        }
    }
};

const handleDelete = () => {
    state.edit_open = false;
    state[state.selectedgroup][state.selectedgroupitem].products.splice(
        state.selected,
        1
    );
    state.selected = null;
    state.selectedgroup = null;
    state.selecteditems = null;
    state.selectedgroupitem = null;
};
const closeModal = () => {
    state.edit_open = false;
    state.selected = null;
};
const closeModal2 = () => {
    state.edit_open2 = false;
    state.selected_group = null;
};
const closeModal1 = () => {
    state.edit_open1 = false;
    state.selected_po = null;
};
const handleAction1 = (data) => {
    state.edit_open1 = false;
    if (data !== null) {
        if (state.selected_po == null) {
            data.products = [];
            state.po_deposits.push(data);
        } else {
            state.po_deposits[state.selected_po] = {
                ...state.po_deposits[state.selected_po],
                ...data,
            };
        }

        state.po_deposits.map((g, i) => {
            g.job_number =
                state.job_number + "-CD" + (i + 1).toString().padStart(3, "0");
            return g;
        });
    }
    state.selected_po = null;
};
const handleAction2 = (data) => {
    state.edit_open2 = false;
    if (data !== null) {
        if (state.selected_group == null) {
            data.products = [];
            data.sent_to_del_at = null;
            data.total_price = null;
            data.client_po_number = state.selected_deposit;
            state.products_group.push(data);
        } else {
            state.products_group[state.selected_group] = {
                ...state.products_group[state.selected_group],
                ...data,
            };
        }
        state.products_group.map((g, i) => {
            g.job_number =
                state.job_number + "-" + (i + 1).toString().padStart(3, "0");
            return g;
        });
    }
    state.selected_po = null;
};
const handleAction = (data) => {
    state.edit_open = false;
    if (data !== null) {
        if (state.selected == null) {
            state[state.selectedgroup][state.selectedgroupitem].products.push(
                data
            );
        } else {
            state[state.selectedgroup][state.selectedgroupitem].products[
                state.selected
            ] = {
                ...state[state.selectedgroup][state.selectedgroupitem].products[
                    state.selected
                ],
                ...data,
            };
        }
    }
    state.selected = null;
    state.selectedgroup = null;
    state.selecteditems = null;
    state.selectedgroupitem = null;
};
const submit = () => {
    if (state.po_deposits?.length < 1) return;
    if (state.po_deposits.every((e) => !e.products)) return;
    // if (state.products_group?.length < 1) return;
    // if (state.products_group.every((e) => !e.products)) return;
    loading();

    // Process products to include UOM and Tax codes
    const processedProductsGroup = state.products_group.map(group => {
        return {
            ...group,
            products: group.products.map(product => {
                return {
                    ...product,
                    // Include UOM and Tax codes if they exist
                    uom_code: product.uom_code || null,
                    tax_code: product.tax_code || null
                };
            })
        };
    });

    const processedPoDeposits = state.po_deposits.map(deposit => {
        return {
            ...deposit,
            products: deposit.products.map(product => {
                return {
                    ...product,
                    // Include UOM and Tax codes if they exist
                    uom_code: product.uom_code || null,
                    tax_code: product.tax_code || null
                };
            })
        };
    });

    let data = {
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        closed_at: state.closed_at,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_code: state.client_code,
        client_pic_name: state.client_pic_name,
        status: state.status,
        budget: state.budget ?? 0,
        expense: state.expense ?? 0,
        balance: state.balance ?? 0,
        products_group: processedProductsGroup,
        po_deposits: processedPoDeposits,
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
// Function to load UOM and Tax data from accounting API
async function loadUomAndTaxData() {
    try {
        // Fetch UOM data using service function
        const uomResult = await getUomList();
        if (uomResult.code === 200) {
            uoms.value = uomResult.data;
        }

        // Fetch Tax data using service function
        const taxResult = await getTaxList();
        if (taxResult.code === 200) {
            taxes.value = taxResult.data;
        }
    } catch (error) {
        console.error('Error loading UOM and Tax data:', error);
    }
}

const init = () => {
    loading();
    getClientList().then((r) => {
        if (r.code === 200) {
            state.clients = r.data;
        }
    });

    // Load UOM and Tax data from accounting API
    loadUomAndTaxData();

    state.pic_name = currentUser.user.user.name;
    // addNewGroup();
    // addNewDeposit();
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
                state.client_code = data.client_code;
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
                            products: e.products_data.map((ee) => ({
                                ...ee,
                                is_real: e.is_real,
                                // Map ID fields to code fields if they exist
                                uom_code: ee.uom_code || (ee.unit_id ? ee.unit_id : null),
                                tax_code: ee.tax_code || (ee.tax_id ? ee.tax_id : null)
                            })),
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
                    if (data.status == "close") {
                        document
                            .querySelectorAll(
                                ".proform input, .proform textarea, .proform .input"
                            )
                            .forEach((el) => {
                                el.setAttribute("disabled", true);
                                // el.classList.remove("bg-white");
                                // el.classList.remove("bg-transparent");
                                // el.classList.add("bg-gray-50");
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
const addNewGroup = () => {
    state.products_group.push({
        title: null,
        job_number: null,
        sent_to_del_at: null,
        total_price: null,
        products: [
            {
                name: null,
                quantity: null,
                description: null,
                price: null,
                total_price: null,
                is_production: true,
                uom_code: null,
                tax_code: null,
            },
        ],
    });
    state.products_group.map((g, i) => {
        g.job_number =
            state.job_number + "-" + (i + 1).toString().padStart(3, "0");
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
                uom_code: null,
                tax_code: null,
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
                if (g.products.length) {
                    g.products.map((p) => {
                        if (p.price != null && p.quantity != null) {
                            p.total_price = p.price * p.quantity;
                        } else {
                            p.total_price = null;
                        }
                        return p;
                    });
                    g.total_price = g.products
                        .map((e) => e.total_price)
                        .reduce((e, c) => e + c);
                } else {
                    g.total_price = 0;
                }
                return g;
            });
            state.expense = group
                .map((e) => e.total_price)
                .reduce((e, c) => e + c);
            state.balance = state.budget - state.expense ?? 0;
        }
    },
    { deep: true }
);
watch(
    () => state.po_deposits,
    (group) => {
        if (group.length) {
            group.map((g) => {
                if (g.products.length) {
                    g.products.map((p) => {
                        if (p.price != null && p.quantity != null) {
                            p.total_price = p.price * p.quantity;
                        } else {
                            p.total_price = null;
                        }
                        return p;
                    });
                    g.total_price = g.products
                        .map((e) => e.total_price)
                        .reduce((e, c) => e + c);
                } else {
                    g.total_price = 0;
                }
                return g;
            });
            state.client_po_number = group[0].client_po_number;
            state.client_po_date = group[0].client_po_date;
            state.budget = group
                .map((e) => e.total_price)
                .reduce((e, c) => e + c);
            state.balance = state.budget - state.expense ?? 0;
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
    <form
        @submit.prevent="submit"
        class="flex flex-col h-full text-black proform"
    >
        <Navbar
            :title="`${state.mode == 'add' ? 'Add New' : 'Edit'} PO Deposit`"
            :back="'/podeposits/?filter=true'"
        ></Navbar>
        <div class="flex-1 flex flex-col overflow-auto">
            <div class="flex-1 flex flex-col">
                <div
                    class="min-h-full min-w-full flex flex-col bg-gray-50 flex-1"
                >
                    <div
                        class="rounded-none bg-white border-b flex flex-col py-2"
                    >
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Job Number *</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.job_number"
                                        required
                                        :disabled="
                                            (user.position != 'marketing' &&
                                                user.position != 'admin') ||
                                            state.status !== 'open'
                                        "
                                    />
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Marketing PIC Name *</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.pic_name"
                                        required
                                        :disabled="
                                            (user.position != 'marketing' &&
                                                user.position != 'admin') ||
                                            state.status !== 'open'
                                        "
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start w-full">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Client Company *</span
                                    >
                                    <Select2
                                        @select="(e) => { 
                                            state.client_company = e.name; 
                                            state.client_code = e.code; 
                                            if(e.contact_person) state.client_pic_name = e.contact_person; 
                                        }"
                                        :disabled="
                                            (user.position != 'marketing' &&
                                                user.position != 'admin') ||
                                            state.status !== 'open'
                                        "
                                        :value="state.client_company"
                                        required="true"
                                    />
                                </label>
                            </div>
                        </div>
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Client PIC *</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.client_pic_name"
                                        required
                                        :disabled="
                                            (user.position != 'marketing' &&
                                                user.position != 'admin') ||
                                            state.status !== 'open'
                                        "
                                    />
                                </label>
                            </div>
                        </div>
                        <div class="flex py-2 justify-between">
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Total</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        :value="
                                            user.position == 'delivery'
                                                ? 0
                                                : nom(state.expense ?? 0)
                                        "
                                        disabled
                                    />
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Remaining Budget</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        :value="
                                            user.position == 'delivery'
                                                ? 0
                                                : nom(state.balance ?? 0)
                                        "
                                        disabled
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-2 text-sm font-bold mt-1">
                        Deposits *
                    </div>
                    <div class="w-full h-1 bg-red-500 my-2"></div>

                    <template v-for="(deposit, i) in state.po_deposits">
                        <div class="px-4 py-2 text-sm font-bold">
                            Non Actual
                        </div>
                        <div
                            class="bg-white p-4"
                            @click="
                                (user.position == 'marketing' ||
                                    user.position == 'admin') &&
                                state.status == 'open'
                                    ? handleDeposit(i)
                                    : null
                            "
                        >
                            <div class="flex mb-2">
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >PO Number</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        deposit.client_po_number
                                    }}</span>
                                </div>
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >PO Date</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        deposit.client_po_date
                                    }}</span>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >PIC</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        deposit.client_pic_name
                                    }}</span>
                                </div>
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >Subtotal</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        user.position == "delivery"
                                            ? 0
                                            : nom(deposit.total_price ?? 0)
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <hr />
                        <div class="pb-4">
                            <button
                                type="button"
                                v-for="(item, ii) in deposit.products"
                                :key="i"
                                class="flex flex-col items-center w-full px-4 bg-white py-3 gap-1 border-b"
                                @click="
                                    () =>
                                        (user.position == 'marketing' ||
                                            user.position == 'admin') &&
                                        state.status == 'open'
                                            ? handleSelected(
                                                  'po_deposits',
                                                  i,
                                                  ii
                                              )
                                            : null
                                "
                            >
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <span>
                                        {{ item.name }}
                                    </span>
                                </div>
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <span>
                                        Rp.
                                        {{
                                            user.position == "delivery"
                                                ? 0
                                                : nom(item.price ?? 0)
                                        }}
                                        x
                                        {{ nom(item.quantity ?? 0) }}
                                    </span>
                                    <strong
                                        >Rp.
                                        {{
                                            user.position == "delivery"
                                                ? 0
                                                : nom(item.total_price ?? 0)
                                        }}
                                    </strong>
                                </div>
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <div class="text-left text-xs text-gray-500">
                                        UOM:
                                    </div>
                                    <div class="text-right text-xs">
                                        {{ item.uom_code || '-' }}
                                    </div>
                                </div>
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <div class="text-left text-xs text-gray-500">
                                        Tax:
                                    </div>
                                    <div class="text-right text-xs">
                                        {{ item.tax_code || '-' }}
                                    </div>
                                </div>
                                <div
                                    class="flex justify-start w-full text-xs text-gray-400 text-left leading-4"
                                    v-html="
                                        item.description?.replace(
                                            /(?:\r\n|\r|\n)/g,
                                            '<br>'
                                        )
                                    "
                                ></div>
                            </button>

                            <div
                                class="p-4 w-full pb-0 flex gap-2"
                                v-if="
                                    (user.position == 'marketing' ||
                                        user.position == 'admin') &&
                                    state.status == 'open'
                                "
                            >
                                <button
                                    type="button"
                                    class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                                    @click="
                                        handleSelectedd(
                                            deposit?.client_po_number
                                        )
                                    "
                                >
                                    <i class="ri-add-box-line text-lg"></i>
                                    <span class="text-xs">
                                        Add New Actual
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="flex p-2 gap-2 items-center justify-center bg-gray-200 text-black w-full rounded"
                                    @click="handleSelected('po_deposits', i)"
                                >
                                    <i class="ri-add-box-line text-lg"></i>
                                    <span class="text-xs"> Add More Item </span>
                                </button>
                            </div>
                        </div>
                        <div class="px-4 py-2 text-sm font-bold">Actual</div>
                        <template
                            v-for="(group, iii) in state.products_group.filter(
                                (e) =>
                                    e.client_po_number ==
                                    deposit.client_po_number
                            )"
                        >
                            <div
                                class="bg-white p-4"
                                @click="
                                    group?.sent_to_del_at == null &&
                                    (user.position == 'marketing' ||
                                        user.position == 'admin') &&
                                    state.status == 'open'
                                        ? handleGroup(
                                              state.products_group.findIndex(
                                                  (e) =>
                                                      e.job_number ==
                                                      group.job_number
                                              )
                                          )
                                        : null
                                "
                            >
                                <div class="flex mb-2">
                                    <div class="w-full flex flex-col">
                                        <span class="text-xs text-gray-400"
                                            >Title</span
                                        >
                                        <span class="text-sm text-gray-600">{{
                                            group.title
                                        }}</span>
                                    </div>
                                    <div class="w-full flex flex-col">
                                        <span class="text-xs text-gray-400"
                                            >Job Number</span
                                        >
                                        <span class="text-sm text-gray-600">{{
                                            group.job_number
                                        }}</span>
                                    </div>
                                </div>
                                <div class="flex">
                                    <div class="w-full flex flex-col">
                                        <span class="text-xs text-gray-400"
                                            >Sent to Delivery</span
                                        >
                                        <span class="text-sm text-gray-600">{{
                                            group.sent_to_del_at
                                        }}</span>
                                    </div>
                                    <div class="w-full flex flex-col">
                                        <span class="text-xs text-gray-400"
                                            >Subtotal</span
                                        >
                                        <span class="text-sm text-gray-600">{{
                                            user.position == "delivery"
                                                ? 0
                                                : nom(group.total_price ?? 0)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div class="pb-4">
                                <button
                                    type="button"
                                    v-for="(item, ii) in group.products"
                                    :key="i"
                                    class="flex flex-col items-center w-full px-4 bg-white py-3 gap-1 border-b"
                                    @click="
                                        () =>
                                            group?.sent_to_del_at == null &&
                                            state.manufacture == null &&
                                            (user.position == 'marketing' ||
                                                user.position == 'admin') &&
                                            state.status == 'open'
                                                ? handleSelected(
                                                      'products_group',
                                                      state.products_group.findIndex(
                                                          (e) =>
                                                              e.job_number ==
                                                              group.job_number
                                                      ),
                                                      ii
                                                  )
                                                : null
                                    "
                                >
                                    <div
                                        class="flex justify-between w-full text-sm"
                                    >
                                        <span>
                                            {{ item.name }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex justify-between w-full text-sm"
                                    >
                                        <span>
                                            Rp.
                                            {{
                                                user.position == "delivery"
                                                    ? 0
                                                    : nom(item.price ?? 0)
                                            }}
                                            x
                                            {{ nom(item.quantity ?? 0) }}
                                        </span>
                                        <strong
                                            >Rp.
                                            {{
                                                user.position == "delivery"
                                                    ? 0
                                                    : nom(item.total_price ?? 0)
                                            }}
                                        </strong>
                                    </div>
                                    <div
                                        class="flex justify-between w-full text-sm"
                                    >
                                        <div class="text-left text-xs text-gray-500">
                                            UOM:
                                        </div>
                                        <div class="text-right text-xs">
                                            {{ item.uom_code || '-' }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex justify-between w-full text-sm"
                                    >
                                        <div class="text-left text-xs text-gray-500">
                                            Tax:
                                        </div>
                                        <div class="text-right text-xs">
                                            {{ item.tax_code || '-' }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex justify-start w-full text-xs text-gray-400 text-left leading-4"
                                        v-html="
                                            item.description?.replace(
                                                /(?:\r\n|\r|\n)/g,
                                                '<br>'
                                            )
                                        "
                                    ></div>
                                </button>

                                <div
                                    class="p-4 w-full pb-0 flex gap-4"
                                    v-if="
                                        group.sent_to_del_at == null &&
                                        (user.position == 'marketing' ||
                                            user.position == 'admin') &&
                                        state.status == 'open'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded w-full"
                                        @click="
                                            () =>
                                                sendToDel(
                                                    state.products_group.findIndex(
                                                        (e) =>
                                                            e.job_number ==
                                                            group.job_number
                                                    )
                                                )
                                        "
                                    >
                                        <i
                                            class="ri-share-forward-2-line text-lg"
                                        ></i>
                                        <span class="text-xs">
                                            Send to Delivery
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex p-2 gap-2 items-center justify-center bg-gray-200 text-black w-full rounded w-full"
                                        @click="
                                            handleSelected(
                                                'products_group',
                                                state.products_group.findIndex(
                                                    (e) =>
                                                        e.job_number ==
                                                        group.job_number
                                                )
                                            )
                                        "
                                    >
                                        <i class="ri-add-box-line text-lg"></i>
                                        <span class="text-xs">
                                            Add More Item
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div class="w-full h-1 bg-red-500 my-2"></div>
                    </template>
                    <div
                        class="p-4 w-full pt-0"
                        v-if="
                            state.status != 'close' &&
                            (user.position == 'marketing' ||
                                user.position == 'admin') &&
                            state.status == 'open'
                        "
                    >
                        <button
                            type="button"
                            class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                            @click="handleDeposit()"
                        >
                            <i class="ri-play-list-add-line text-lg"></i>
                            <span class="text-xs"> Add New Deposit </span>
                        </button>
                    </div>
                    <hr />
                    <!-- <div class="px-4 py-2 text-sm font-bold">Actual</div>
                    <template v-for="(group, i) in state.products_group">
                        <div class="bg-white p-4" @click="handleGroup(i)">
                            <div class="flex mb-2">
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >Title</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        group.title
                                    }}</span>
                                </div>
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >Job Number</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        group.job_number
                                    }}</span>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >Sent to Delivery</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        group.sent_to_del_at
                                    }}</span>
                                </div>
                                <div class="w-full flex flex-col">
                                    <span class="text-xs text-gray-400"
                                        >Subtotal</span
                                    >
                                    <span class="text-sm text-gray-600">{{
                                        group.total_price
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <hr />
                        <div class="pb-4">
                            <button
                                type="button"
                                v-for="(item, ii) in group.products"
                                :key="i"
                                class="flex flex-col items-center w-full px-4 bg-white py-3 gap-1 border-b"
                                @click="
                                    () =>
                                        state.manufacture == null &&
                                        state.status == 'open'
                                            ? handleSelected(
                                                  'products_group',
                                                  i,
                                                  ii
                                              )
                                            : null
                                "
                            >
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <span>
                                        {{ item.name }}
                                    </span>
                                </div>
                                <div
                                    class="flex justify-between w-full text-sm"
                                >
                                    <span>
                                        Rp. {{ nom(item.price ?? 0) }} x
                                        {{ nom(item.quantity ?? 0) }}
                                    </span>
                                    <strong
                                        >Rp. {{ nom(item.total_price ?? 0) }}
                                    </strong>
                                </div>
                                <div
                                    class="flex justify-start w-full text-xs text-gray-400 text-left leading-4"
                                    v-html="
                                        item.description?.replace(
                                            /(?:\r\n|\r|\n)/g,
                                            '<br>'
                                        )
                                    "
                                ></div>
                            </button>

                            <div
                                class="p-4 w-full pb-0 flex gap-4"
                                v-if="group.sent_to_del_at == null"
                            >
                                <button
                                    type="button"
                                    class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded w-full"
                                    @click="() => sendToDel(i)"
                                >
                                    <i
                                        class="ri-share-forward-2-line text-lg"
                                    ></i>
                                    <span class="text-xs">
                                        Send to Delivery
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="flex p-2 gap-2 items-center justify-center bg-gray-200 text-black w-full rounded w-full"
                                    @click="handleSelected('products_group', i)"
                                >
                                    <i class="ri-add-box-line text-lg"></i>
                                    <span class="text-xs"> Add More Item </span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <div
                        class="p-4 w-full pt-0"
                        v-if="
                            state.products_group.every(
                                (e) => e.sent_to_del_at !== null
                            ) && state.status != 'close'
                        "
                    >
                        <button
                            type="button"
                            class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                            @click="handleGroup()"
                        >
                            <i class="ri-play-list-add-line text-lg"></i>
                            <span class="text-xs"> Add New Group </span>
                        </button>
                    </div> -->
                </div>
            </div>
            <div
                class="p-4 w-full pt-0"
                v-if="
                    currentUrl.includes('edit') &&
                    state.status != 'close' &&
                    state.products_group.length > 0 &&
                    state.products_group.every((e) => e.sent_to_del_at != null)
                "
            >
                <button
                    type="button"
                    class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                    @click="closePO"
                >
                    <span class="text-xs"> Close This PO </span>
                </button>
            </div>
        </div>

        <div
            class="px-4 py-2 flex items-center justify-center w-full bg-white border-t"
            v-if="
                state.status != 'close' &&
                (user.position == 'marketing' || user.position == 'admin')
            "
        >
            <button
                class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
            >
                <i class="ri-save-line text-xl"></i>
                <span class="text-sm mt-1"> Save </span>
            </button>
        </div>
    </form>
    <Alert
        type="fail"
        title="Failed to save"
        :content="alertShowFailed ?? ''"
        :show="alertShowFailed != false"
        @hide="alertShowFailed = false"
    ></Alert>
    <Alert
        type="success"
        title="Success to save"
        :content="'PO Deposit saved successfully'"
        :show="alertShowSuccess != false"
        @hide="
            () => {
                alertShowSuccess = false;
                router.back();
            }
        "
    ></Alert>

    <ItemModal2
        :show="state.edit_open"
        :items="state.selecteditems"
        :selected="state.selected"
        :uoms="uoms"
        :taxes="taxes"
        @hide="closeModal"
        @action="handleAction"
        @delete="handleDelete"
    ></ItemModal2>
    <ItemModal3
        :show="state.edit_open1"
        :items="state.po_deposits"
        :selected="state.selected_po"
        :productGroups="state.products_group"
        @hide="closeModal1"
        @action="handleAction1"
        @delete="handleDeleteGroup('deposit')"
    ></ItemModal3>
    <ItemModal4
        :show="state.edit_open2"
        :items="state.products_group"
        :selected="state.selected_group"
        @hide="closeModal2"
        @action="handleAction2"
        @delete="handleDeleteGroup('project')"
    ></ItemModal4>
    <!-- @delete="handleDelete1" -->
</template>
