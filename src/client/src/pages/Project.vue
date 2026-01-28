<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, computed, watch, nextTick } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    nom,
    getUomList,
    getTaxList
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Navbar from "../components/Navbar.vue";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
import ItemModal1 from "../components/ItemModal1.vue";
import Select2 from "../components/Select2.vue";
const confirmDelete = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);

const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    edit_open: false,
    mode: "add",
    on_process: false,
    selected: null,
    clients: [],
    errors: {},
    id: null,
    title: null,
    job_number: null,
    client_po_date: null,
    pic_name: null,
    total_price: null,
    manual_total_price: false, // Flag to indicate if user wants to manually enter total price
    client_po_number: null,
    client_company: null,
    client_pic_name: null,
    manufacture: null,
    status: "production",
    products: [],
});

// Reactive variables for UOM and Tax options
const uoms = ref([]);
const taxes = ref([]);

// Computed property to calculate total price based on all products
const calculatedTotalPrice = computed(() => {
    return state.products.reduce((sum, product) => {
        if (product.quantity && product.price) {
            return sum + (parseInt(product.quantity) * parseInt(product.price));
        }
        return sum;
    }, 0);
});

// Watcher to update total_price when in auto mode
watch(calculatedTotalPrice, (newVal) => {
    if (!state.manual_total_price) {
        // Only update if the current value is different from calculated value
        if (state.total_price !== newVal) {
            state.total_price = newVal;
        }
    }
}, { immediate: true });

const closeModal = () => {
    state.edit_open = false;
    state.selected = null;
};

// Track if the focus event should trigger manual mode
const shouldSwitchToManual = ref(true);

const onTotalPriceFocus = async (event) => {
    // Wait for any pending DOM updates
    await nextTick();

    if (!state.manual_total_price && shouldSwitchToManual.value) {
        // When user focuses on the field while in auto mode, switch to manual mode
        state.manual_total_price = true;
        // Set the value to the calculated value when switching to manual
        if (state.total_price === null || state.total_price === undefined || state.total_price === "") {
            state.total_price = calculatedTotalPrice.value;
        }
    }
    // Reset the flag after processing
    shouldSwitchToManual.value = true;
};

const toggleManualTotalPrice = () => {
    state.manual_total_price = !state.manual_total_price;
    // Prevent the focus event from switching to manual when toggle is clicked
    shouldSwitchToManual.value = false;
    if (!state.manual_total_price) {
        // Switch back to auto-calculated value
        state.total_price = calculatedTotalPrice.value;
    } else {
        // When switching to manual, preserve the current value or set to calculated if null
        if (state.total_price === null || state.total_price === undefined || state.total_price === "") {
            state.total_price = calculatedTotalPrice.value;
        }
    }
};

const onTotalPriceBlur = () => {
    // On blur, validate the input and ensure it's a number
    if (state.total_price !== null && state.total_price !== undefined && state.total_price !== "") {
        state.total_price = parseFloat(state.total_price) || 0;
    }
};
const handleSelected = (i = null) => {
    state.edit_open = true;
    if (i !== null) {
        state.selected = i;
    }
};
const handleDelete = () => {
    state.edit_open = false;
    state.products.splice(state.selected, 1);
    state.selected = null;
};
const handleAction = (data) => {
    state.edit_open = false;
    if (data !== null) {
        if (state.selected == null) {
            state.products.push(data);
        } else {
            state.products[state.selected] = {
                ...state.products[state.selected],
                ...data,
            };
        }
    }
    state.selected = null;
};
const submit = () => {
    loading();

    // Determine the final total price based on whether user chose manual or auto calculation
    let finalTotalPrice;
    if (state.manual_total_price) {
        // Use the manually entered value
        finalTotalPrice = state.total_price !== null && state.total_price !== undefined && state.total_price !== ""
            ? parseFloat(state.total_price)
            : 0;
    } else {
        // Use the calculated value
        finalTotalPrice = calculatedTotalPrice.value;
    }

    // Process products to include UOM and Tax codes
    const processedProducts = state.products.map(product => {
        return {
            ...product,
            // Include UOM and Tax codes if they exist
            uom_code: product.uom_code || null,
            tax_code: product.tax_code || null
        };
    });

    let data = {
        title: state.title,
        total_price: finalTotalPrice, // Use manual input or calculated value
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_pic_name: state.client_pic_name,
        status: state.status,
        products: processedProducts,
    };
    if (state.id === null) {
        createProject(data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowFailed.value = r.errors.join(" ");
            }
        });
    } else {
        updateProject(state.id, data).then((r) => {
            loading(false);
            if (r.code === 200) {
                alertShowSuccess.value = true;
            } else {
                alertShowFailed.value = r.errors.join(" ");
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

onMounted(() => {
    state.pic_name = currentUser.user.user.name;

    // Load UOM and Tax data from accounting API
    loadUomAndTaxData();

    if (currentUrl.includes("edit")) {
        state.mode = "edit";
        loading();
        let id = currentUrl.split("/").slice(-1);
        getProject(id).then((r) => {
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
                state.title = data.title;
                state.total_price = data.total_price;
                state.manufacture = data.manufacture;
                state.is_po_deposit = data.is_po_deposit;

                // Initialize products with price and total_price properties
                state.products = data.products_data.map(product => ({
                    ...product,
                    price: product.price || 0,
                    total_price: product.total_price || (product.quantity * (product.price || 0)),
                    // Map ID fields to code fields if they exist
                    uom_code: product.uom_code || (product.unit_id ? product.unit_id : null),
                    tax_code: product.tax_code || (product.tax_id ? product.tax_id : null)
                }));

                if (data.client_po_number == null) {
                    state.on_process = true;
                }
                setTimeout(() => {
                    if (data.is_po_deposit) {
                        document
                            .querySelectorAll(
                                ".proform input, .proform button "
                            )
                            .forEach((el) => {
                                el.setAttribute("disabled", true);
                            });
                    }
                }, 500);
            }
        });
    }
});
</script>
<template>
    <form
        @submit.prevent="submit"
        class="flex flex-col h-full text-black proform"
    >
        <Navbar
            :title="`${state.mode == 'add' ? 'Add New' : 'Edit'} Project`"
            :back="'/projects/?filter=true'"
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
                                        >Job Number</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.job_number"
                                        required
                                    />
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >Title</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.title"
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
                                        >Marketing PIC Name</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.pic_name"
                                        required
                                    />
                                </label>
                            </div>
                            <div class="flex flex-col items-start flex-1">
                                <div class="flex flex-col items-start gap-1 px-4 w-full">
                                    <span class="text-xs text-blue-gray-400 flex justify-between w-full">
                                        <span>Omzet</span>
                                        <span class="text-blue-500 text-xs cursor-pointer" @click.stop="toggleManualTotalPrice">
                                            {{ state.manual_total_price ? 'Auto' : 'Manual' }}
                                        </span>
                                    </span>
                                    <input
                                        type="number"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.total_price"
                                        :readonly="!state.manual_total_price"
                                        @focus="onTotalPriceFocus"
                                        @blur="onTotalPriceBlur"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="h-10 w-full flex rounded border">
                                <button
                                    type="button"
                                    class="flex-1 h-full text-xs flex items-center justify-center rounded-l"
                                    @click="() => (state.on_process = false)"
                                    :class="
                                        !state.on_process
                                            ? 'bg-red-500 text-white'
                                            : ''
                                    "
                                >
                                    PO Number
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 h-full text-xs flex items-center justify-center rounded-r"
                                    @click="() => (state.on_process = true)"
                                    :class="
                                        state.on_process
                                            ? 'bg-red-500 text-white'
                                            : ''
                                    "
                                >
                                    PO Dalam Process
                                </button>
                            </div>
                        </div>
                        <div
                            class="flex py-2 justify-between"
                            v-if="!state.on_process"
                        >
                            <div class="flex flex-col items-start flex-1">
                                <label
                                    class="flex flex-col items-start gap-1 px-4 w-full"
                                >
                                    <span class="text-xs text-blue-gray-400"
                                        >PO Number</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.client_po_number"
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
                                        >PO Date</span
                                    >
                                    <input
                                        type="date"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.client_po_date"
                                        required
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
                                        >Client Company</span
                                    >
                                    <Select2
                                        @select="
                                            (e) => (state.client_company = e)
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
                                        >Client PIC</span
                                    >
                                    <input
                                        type="text"
                                        class="border-b w-full h-10 bg-transparent text-sm text-black"
                                        v-model="state.client_pic_name"
                                        required
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-2 text-sm font-bold">Products</div>
                    <div class="h-full">
                        <button
                            type="button"
                            v-for="(item, i) in state.products"
                            :key="i"
                            class="flex flex-col items-center w-full px-4 bg-white py-3 gap-1 border-b"
                            @click="
                                () =>
                                    state.manufacture == null
                                        ? handleSelected(i)
                                        : null
                            "
                        >
                            <div class="flex justify-between w-full text-sm">
                                <span>
                                    {{ item.name }}
                                </span>
                                <div class="text-right">
                                    <div><strong>{{ nom(item.quantity) }} </strong></div>
                                    <div class="text-xs">{{ nom(item.price) }}</div>
                                </div>
                            </div>
                            <div class="flex justify-between w-full text-sm">
                                <div class="text-left text-xs text-gray-500">
                                    UOM:
                                </div>
                                <div class="text-right text-xs">
                                    {{ item.uom_code || '-' }}
                                </div>
                            </div>
                            <div class="flex justify-between w-full text-sm">
                                <div class="text-left text-xs text-gray-500">
                                    Tax:
                                </div>
                                <div class="text-right text-xs">
                                    {{ item.tax_code || '-' }}
                                </div>
                            </div>
                            <div class="flex justify-between w-full text-sm">
                                <div class="text-left text-xs text-gray-500">
                                    Subtotal:
                                </div>
                                <div class="text-right text-sm font-semibold">
                                    {{ nom(item.total_price) }}
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
                            class="p-4 w-full mb-4"
                            v-if="
                                state.manufacture == null &&
                                !state.is_po_deposit
                            "
                        >
                            <button
                                type="button"
                                class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white w-full rounded"
                                @click="handleSelected()"
                            >
                                <i class="ri-add-line text-xl"></i>
                                <span class="text-sm"> Add more item </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="px-4 py-2 flex items-center justify-center w-full bg-white border-t"
            v-if="state.manufacture == null && !state.is_po_deposit"
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
        :content="'Project saved successfully'"
        :show="alertShowSuccess != false"
        @hide="
            () => {
                alertShowSuccess = false;
                router.back();
            }
        "
    ></Alert>

    <ItemModal1
        :show="state.edit_open"
        :items="state.products"
        :selected="state.selected"
        :uoms="uoms"
        :taxes="taxes"
        @hide="closeModal"
        @action="handleAction"
        @delete="handleDelete"
    ></ItemModal1>
</template>
