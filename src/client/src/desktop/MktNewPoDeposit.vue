<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref, watch } from "vue";
import SelectProduct from "../components/SelectProduct.vue";
import Swal from 'sweetalert2';
import {
    getProject,
    createPodeposit,
    updatePodeposit,
    currentUser,
    nom,
    getPodeposit,
    goto,
    getUomList,
    getTaxList
} from "../services/service";
import Select1 from "../components/Select1.vue";
import INumber from "../components/INumber.vue";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";

const props = defineProps({
    layoutData: {
        type: Object,
    },
});

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
    client_code: null,
    client_pic_name: null,
    ppn_type: 'ppn', // ppn or non_ppn
    budget: null,
    expense: null,
    balance: null,
    status: "open",
    products_group: [],
    selectedGroup: null,
    po_deposits: [],
    current_po: 0,
    documents: {
        do: false,
        bast: false,
        gr: false,
    }
});

// Reactive variables for UOM and Tax options
const uoms = ref([]);
const taxes = ref([]);

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
// Handle product selection from SelectProduct component
// Helper function to calculate price with tax
const calculatePriceWithTax = (price, taxCode) => {
    if (!taxCode) return price;

    // Find the tax object by code to get the percentage
    const taxObj = taxes.value?.find(tax => tax.code === taxCode);
    if (!taxObj || !taxObj.tax_percentage) return price;

    // Calculate the price including tax
    const taxPercentage = parseFloat(taxObj.tax_percentage);
    const total = price + (price * taxPercentage / 100);
    // Round to 2 decimal places to avoid floating-point precision issues
    return Math.round(total * 100) / 100;
};

const onProductSelect = (product, selectedData) => {
    if (selectedData && selectedData.name) {
        product.name = selectedData.name;
        product.product_code = selectedData.code;
        product.price = selectedData.selling_price || 0;
        product.description = selectedData.description || '';
        // Clear and set UOM code
        product.uom_code = selectedData.unit ? selectedData.unit.code : null;
        // Clear and set tax code
        product.tax_code = selectedData.tax ? selectedData.tax.code : null;
    } else {
        // If no selected data or no name, clear the fields
        product.name = null;
        product.product_code = null;
        product.price = null;
        product.description = null;
        product.uom_code = null;
        product.tax_code = null;
    }
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
            if (p.price != null && p.quantity != null) {
                const priceWithTax = calculatePriceWithTax(parseFloat(p.price), p.tax_code);
                p.total_price = Math.round(parseFloat(p.quantity) * priceWithTax * 100) / 100;
            } else {
                p.total_price = null;
            }
            return p;
        });
        g.total_price = Math.round(g.products.reduce(
            (e, c) => e + (c.total_price || 0),
            0
        ) * 100) / 100;
        return g;
    });

    state.po_deposits = state.po_deposits.map((g) => {
        g.products = g.products.map((p) => {
            if (p.price != null && p.quantity != null) {
                const priceWithTax = calculatePriceWithTax(parseFloat(p.price), p.tax_code);
                p.total_price = Math.round(parseFloat(p.quantity) * priceWithTax * 100) / 100;
            } else {
                p.total_price = null;
            }
            return p;
        });
        g.total_price = Math.round(g.products.reduce(
            (e, c) => e + (c.total_price || 0),
            0
        ) * 100) / 100;
        return g;
    });

    state.balance = Math.round((state.budget - state.expense) * 100) / 100 ?? 0;
    state.expense = Math.round(state.products_group.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    ) * 100) / 100;
    state.budget = Math.round(state.po_deposits.reduce(
        (e, c) => e + (c.total_price || 0),
        0
    ) * 100) / 100;
    state.balance = Math.round((state.budget - state.expense) * 100) / 100 ?? 0;

    state.current_po = state.po_deposits.length - 1;

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
    // Validate all products before submitting (all deposits and actual products)
    if (!validateAllProducts(null, true)) {
        return;
    }

    loading();

    // Process products to include UOM and Tax codes
    const actual = state.products_group.map((g) => {
        return {
            ...g,
            documents: state.documents,
            products: g.products.map(product => {
                return {
                    ...product,
                    // Include UOM and Tax codes if they exist
                    uom_code: product.uom_code || null,
                    tax_code: product.tax_code || null
                };
            })
        };
    });

    const nonactual = state.po_deposits.map((g) => {
        return {
            ...g,
            documents: state.documents,
            products: g.products.map(product => {
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
        ppn_type: state.ppn_type,
        status: state.status,
        budget: state.budget ?? 0,
        expense: state.expense ?? 0,
        balance: state.balance ?? 0,
        products_group: actual,
        po_deposits: nonactual,
        is_po_deposit: true,
        documents: state.documents
    };
    // console.log(data);
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
        // Fetch UOM data using service function with ppn_type
        const uomResult = await getUomList(state.ppn_type);
        if (uomResult.code === 200) {
            uoms.value = uomResult.data;
        }

        // Fetch Tax data using service function with ppn_type
        const taxResult = await getTaxList(state.ppn_type);
        if (taxResult.code === 200) {
            taxes.value = taxResult.data;
        }
    } catch (error) {
        console.error('Error loading UOM and Tax data:', error);
    }
}

const init = () => {
    loading();

    // Load UOM and Tax data from accounting API
    loadUomAndTaxData();

    state.pic_name = currentUser.user.user.name;
    addNewDeposit(true);
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
                state.client_code = data.client_code;
                state.client_pic_name = data.client_pic_name;
                state.status = data.status;
                state.ppn_type = data.ppn_type || 'ppn';
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
                            products: e.products_data.map((product) => ({
                                ...product,
                                // Map ID fields to code fields if they exist
                                uom_code: product.uom_code || (product.unit_id ? product.unit_id : null),
                                tax_code: product.tax_code || (product.tax_id ? product.tax_id : null)
                            })),
                        })),
                ];
                state.po_deposits = [
                    ...data.projects_data
                        .filter((e) => !e.is_real)
                        .map((e) => ({
                            ...e,
                            products: e.products_data.map((product) => ({
                                ...product,
                                // Map ID fields to code fields if they exist
                                uom_code: product.uom_code || (product.unit_id ? product.unit_id : null),
                                tax_code: product.tax_code || (product.tax_id ? product.tax_id : null)
                            })),
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
                product_code: null,
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
        g.job_number = state.job_number
            ? state.job_number + "-" + (i + 1).toString().padStart(3, "0")
            : null;
        return g;
    });
};

const validateProducts = (products) => {
    return products.every(p =>
        p.name &&
        p.quantity &&
        p.price &&
        p.description
    );
};

// Comprehensive product validation function similar to MktNewProject.vue
const validateAllProducts = (specificDepositIndex = null, showAlert = true) => {
    // Reset errors before validation
    state.errors = {};

    let hasError = false;

    // Validate products in po_deposits (non-actual)
    state.po_deposits.forEach((group, groupIndex) => {
        // If specificDepositIndex is provided, only validate that deposit
        if (specificDepositIndex !== null && specificDepositIndex !== groupIndex) {
            return; // Skip this iteration
        }

        group.products.forEach((product, productIndex) => {
            // Check if product has a name to determine if it should be validated
            if (product.name && product.name.trim() !== '') {
                const errorKey = `po_deposits.${groupIndex}.products.${productIndex}`;

                // For non-payment products (in deposits), quantity, price, UOM, and tax are required
                if (!product.quantity || parseFloat(product.quantity) <= 0) {
                    state.errors[`${errorKey}.quantity`] = ['Quantity is required and must be greater than 0'];
                    hasError = true;
                }

                if (!product.price || parseFloat(product.price) <= 0) {
                    state.errors[`${errorKey}.price`] = ['Price is required and must be greater than 0'];
                    hasError = true;
                }

                if (!product.uom_code) {
                    state.errors[`${errorKey}.uom_code`] = ['UOM is required'];
                    hasError = true;
                }

                if (!product.tax_code) {
                    state.errors[`${errorKey}.tax_code`] = ['Tax is required'];
                    hasError = true;
                }

                // Description is also required
                if (!product.description || product.description.trim() === '') {
                    state.errors[`${errorKey}.description`] = ['Description is required'];
                    hasError = true;
                }
            }
        });
    });

    // Validate products in products_group (actual) only if not validating specific deposit
    if (specificDepositIndex === null) {
        state.products_group.forEach((group, groupIndex) => {
            group.products.forEach((product, productIndex) => {
                // Check if product has a name to determine if it should be validated
                if (product.name && product.name.trim() !== '') {
                    const errorKey = `products_group.${groupIndex}.products.${productIndex}`;

                    // For non-payment products (in actual), quantity, price, UOM, and tax are required
                    if (!product.quantity || parseFloat(product.quantity) <= 0) {
                        state.errors[`${errorKey}.quantity`] = ['Quantity is required and must be greater than 0'];
                        hasError = true;
                    }

                    if (!product.price || parseFloat(product.price) <= 0) {
                        state.errors[`${errorKey}.price`] = ['Price is required and must be greater than 0'];
                        hasError = true;
                    }

                    if (!product.uom_code) {
                        state.errors[`${errorKey}.uom_code`] = ['UOM is required'];
                        hasError = true;
                    }

                    if (!product.tax_code) {
                        state.errors[`${errorKey}.tax_code`] = ['Tax is required'];
                        hasError = true;
                    }

                    // Description is also required
                    if (!product.description || product.description.trim() === '') {
                        state.errors[`${errorKey}.description`] = ['Description is required'];
                        hasError = true;
                    }
                }
            });
        });
    }

    if (hasError && showAlert) {
        alertShowFailed.value = true;
        return false;
    } else if (hasError) {
        // Just return false without showing alert
        return false;
    }

    return true;
};

const isDepositEmpty = (deposit) => {
    // Check if deposit has no PO number, PIC, or PO date
    const hasNoBasicInfo = !deposit.client_po_number && !deposit.client_pic_name && !deposit.client_po_date;
    // Check if all products are empty (no name, quantity, or price)
    const hasEmptyProducts = deposit.products.every(p => 
        !p.name && !p.quantity && !p.price
    );
    return hasNoBasicInfo && hasEmptyProducts;
};

const switchPO = async (e) => {
    const current = state.po_deposits[state.current_po];
    const newValue = parseInt(e.target.value);
    
    // Check if trying to switch to same deposit
    if (newValue === state.current_po) return;
    
    // Check if current deposit is empty
    if (isDepositEmpty(current)) {
        const result = await Swal.fire({
            icon: 'question',
            title: 'Empty Deposit',
            text: 'This deposit is empty. What would you like to do?',
            showCancelButton: false,
            showDenyButton: true,
            confirmButtonText: 'Delete',
            denyButtonText: 'Complete',
            confirmButtonColor: '#ef4444',
            denyButtonColor: '#3b82f6',
        });
        
        if (result.isConfirmed) {
            // Delete the empty deposit
            state.po_deposits.splice(state.current_po, 1);
            // Adjust current_po if necessary
            if (state.current_po >= state.po_deposits.length) {
                state.current_po = state.po_deposits.length - 1;
            }
            // Now switch to the selected deposit
            if (!isNaN(newValue)) {
                // Adjust newValue if the deleted index was before it
                const adjustedNewValue = newValue > state.current_po ? newValue - 1 : newValue;
                state.current_po = adjustedNewValue;
            }
            // Update select value
            e.target.value = state.current_po;
        } else {
            // User chose to complete - stay and reset select
            e.target.value = state.current_po;
        }
        return;
    }
    
    // Validate current PO fields
    if (!current.client_po_number || !current.client_pic_name || !current.client_po_date) {
        // Reset select to current value since validation failed
        e.target.value = state.current_po;
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: 'Please fill all required fields (PO Number, PIC, PO Date)'
        });
        return;
    }
    // Validate products with comprehensive validation for the current deposit only
    if (!validateAllProducts(state.current_po, false)) {
        // Reset select to current value since validation failed
        e.target.value = state.current_po;
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: 'Please fill all required product fields (name, quantity, price, UOM, tax, and description)'
        });
        return;
    }
    if (!isNaN(newValue)) {
        state.current_po = newValue;
    }
};

const addNewDeposit = async (force = false) => {
    if (!force) {
        const current = state.po_deposits[state.current_po];
        
        // Check if current deposit is empty
        if (isDepositEmpty(current)) {
            const result = await Swal.fire({
                icon: 'question',
                title: 'Empty Deposit',
                text: 'This deposit is empty. What would you like to do?',
                showCancelButton: false,
                showDenyButton: true,
                confirmButtonText: 'Delete',
                denyButtonText: 'Complete',
                confirmButtonColor: '#ef4444',
                denyButtonColor: '#3b82f6',
            });
            
            if (result.isConfirmed) {
                // Delete the empty deposit
                state.po_deposits.splice(state.current_po, 1);
                // Adjust current_po if necessary
                if (state.current_po >= state.po_deposits.length) {
                    state.current_po = state.po_deposits.length - 1;
                }
                // Continue to add new deposit
            } else {
                // User chose to complete - stay on current
                return;
            }
        } else {
            // Validate current PO fields
            if (!current.client_po_number || !current.client_pic_name || !current.client_po_date) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Please fill all required fields (PO Number, PIC, PO Date)'
                });
                return;
            }
            // Validate products with comprehensive validation for the current deposit only
            if (!validateAllProducts(state.current_po, false)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Please fill all required product fields (name, quantity, price, UOM, tax, and description)'
                });
                return;
            }
        }
    }
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
                product_code: null,
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

    state.current_po = state.po_deposits.length - 1;
};
// vue watch state.products_group
watch(
    () => state.products_group,
    (group) => {
        if (group.length) {
            group.map((g) => {
                g.products.map((p) => {
                    if (p.price != null && p.quantity != null) {
                        const priceWithTax = calculatePriceWithTax(parseFloat(p.price), p.tax_code);
                        p.total_price = Math.round(parseFloat(p.quantity) * priceWithTax * 100) / 100;
                    } else {
                        p.total_price = null;
                    }
                    return p;
                });
                g.total_price = Math.round(g.products
                    .filter((e) => e.total_price != null)
                    .reduce((e, c) => e + c.total_price, 0) * 100) / 100;
                return g;
            });
            state.expense = Math.round(group
                .filter((e) => e.total_price != null)
                .reduce((e, c) => e + c.total_price, 0) * 100) / 100;
            state.balance = Math.round((state.budget - state.expense) * 100) / 100 ?? 0;
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
                    if (p.price != null && p.quantity != null) {
                        const priceWithTax = calculatePriceWithTax(parseFloat(p.price), p.tax_code);
                        p.total_price = Math.round(parseFloat(p.quantity) * priceWithTax * 100) / 100;
                    } else {
                        p.total_price = null;
                    }
                });
                g.total_price = Math.round(g.products.reduce(
                    (acc, e) => acc + (e.total_price || 0),
                    0
                ) * 100) / 100;
            });
            state.client_po_number = group[0].client_po_number;
            state.client_po_date = group[0].client_po_date;
            state.budget = Math.round(group.reduce(
                (acc, e) => acc + (e.total_price || 0),
                0
            ) * 100) / 100;
            state.balance = Math.round((state.budget - (state.expense ?? 0)) * 100) / 100;
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

// Watch for ppn_type changes and reload UOM/Tax data
watch(() => state.ppn_type, () => {
    loadUomAndTaxData();
});
</script>

<template>
    <div class="p-8 relative h-[calc(100vh-55px)]">
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
        <form @submit.prevent="submit">
            <div class="w-full bg-white rounded-lg shadow p-8 proform mb-6">
                <div class="flex gap-8">
                    <div class="w-1/2">
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left">Tax Type</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <select
                                    v-model="state.ppn_type"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 text-14px"
                                >
                                    <option value="ppn">PPN</option>
                                    <option value="non_ppn">NON-PPN</option>
                                </select>
                                <div class="absolute left-4 text-red-500 text-xl">
                                    <i class="ri-percent-line"></i>
                                </div>
                            </div>
                        </label>
                        <div class="h-3 flex -mt-1"><!--v-if--></div>
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left">Job Number</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    v-model="state.job_number"
                                    required
                                    :readonly="currentUrl.includes('edit')"
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-briefcase-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'job_number'
                                        )
                                    "
                                    >{{ state.errors?.job_number[0] }}</span
                                >
                            </div>
                        </label>

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
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-user-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty('pic_name')
                                    "
                                    >{{ state.errors?.pic_name[0] }}</span
                                >
                            </div>
                        </label>
                    </div>
                    <div class="w-1/2">
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left"
                                >Client Company</span
                            >
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <Select1
                                    @select="(e) => { state.client_company = e.name; state.client_code = e.code; if(e.contact_person) state.client_pic_name = e.contact_person; }"
                                    :value="state.client_company"
                                    :ppnType="state.ppn_type"
                                    required="true"
                                />

                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
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
                            <span class="text-sm text-left"
                                >Client PIC Name</span
                            >
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.client_pic_name"
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
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
                                    >{{
                                        state.errors?.client_pic_name[0]
                                    }}</span
                                >
                            </div>
                        </label>
                    </div>
                </div>
                <div class="text-sm text-[#667085] my-2">
                    Choose Documents to be Uploaded
                </div>
                <div class="border p-3 flex justify-between rounded-lg mb-4">
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.do"
                        />
                        <span class="text-sm">DO (Delivery Order)</span>
                    </label>
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.bast"
                        />
                        <span class="text-sm"
                            >BAST (Berita Acara Serah Terima)</span
                        >
                    </label>
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.gr"
                        />
                        <span class="text-sm">GR/TBP</span>
                    </label>
                    <span></span>
                </div>
            </div>

            <div class="pb-30">
                <div class="flex mt-2 mb-4 items-center gap-4">
                    <div class="text-left flex flex-col">
                        <span class="text-lg"> Deposit </span>
                    </div>

                    <button
                        type="button"
                        class="h-40px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm hidon"
                        @click="addNewDeposit()"
                        v-if="state.status != 'close'"
                    >
                        <i class="ri-add-line text-xl"></i>
                        <strong>Add New Deposit</strong>
                    </button>

                    <div
                        class="flex items-center gap-4 h-40px rounded-lg border w-240px overflow-hidden bg-white px-2"
                    >
                        <i class="ri-box-3-line text-xl text-red-500"></i>
                        <select
                            class="w-full h-full"
                            @change="switchPO"
                            :value="state.current_po"
                        >
                            <option disabled>Select PO Deposit</option>
                            <option
                                v-for="(po, i) in state.po_deposits"
                                :key="i"
                                :value="i"
                            >
                                {{ po.client_po_number || 'New Deposit' }}
                            </option>
                        </select>
                    </div>
                </div>

                <template v-for="(group, i) in state.po_deposits" :key="i">
                    <template v-if="i == state.current_po">
                        <div
                            class="w-full bg-white rounded-lg shadow p-8 proform mb-6"
                        >
                            <div
                                class="text-left flex items-center justify-between mb-4 h-10"
                            >
                                <span class="text-lg"> Non Actual </span>

                                <button
                                    @click="addNewGroup(group.client_po_number)"
                                    v-if="
                                        state.status != 'close' &&
                                        user.position !== 'delivery' &&
                                        user.position !== 'finance' &&
                                        group.client_po_number
                                    "
                                    type="button"
                                    class="text-sm px-4 py-1 bg-red-500 text-white hover:bg-red-600 items-center justify-center flex gap-4 rounded-lg"
                                >
                                    <i class="ri-add-box-line text-xl"></i>
                                    <strong>Add New Actual</strong>
                                </button>
                            </div>
                            <div class="mb-6">
                                <div class="border rounded-lg">
                                    <table
                                        class="w-full rounded-lg overflow-hidden"
                                    >
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <td
                                                    colspan="9"
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
                                                                                && state.po_deposits.length > 1
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
                                                                    <div v-else class="w-36px">

                                                                    </div>
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
                                                                    Subtotal
                                                                    Budget
                                                                </th>
                                                                <th
                                                                    class="p-1 w-1/3 font-medium text-left"
                                                                >
                                                                    Used Budget
                                                                </th>
                                                                <th
                                                                    class="p-1 w-1/3 font-medium text-left"
                                                                >
                                                                    Remaining
                                                                    Budget
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
                                                    class="text-left text-sm px-1 py-2 w-1/6"
                                                >
                                                    Product Code
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-1/6"
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
                                                    UOM
                                                </th>
                                                <th
                                                    class="text-left text-sm px-1 py-2 w-120px"
                                                >
                                                    Tax
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
                                                        <SelectProduct
                                                            :value="product.product_code"
                                                            @select="(data) => onProductSelect(product, data)"
                                                            :ppnType="state.ppn_type"
                                                            :disabled="group.manufacture != null"
                                                            required
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `po_deposits.${i}.products.${ii}.product_code`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.product_code`
                                                                ][0]
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
                                                            class="w-full h-40px px-1 text-sm bg-transparent border-none rounded-lg"
                                                            placeholder="Item name"
                                                            v-model="product.name"
                                                        />
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `po_deposits.${i}.products.${ii}.name`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.name`
                                                                ][0]
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <INumber
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
                                                                    `po_deposits.${i}.products.${ii}.quantity`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.quantity`
                                                                ][0]
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <select
                                                            class="w-full h-40px px-1 text-sm bg-transparent border-none"
                                                            v-model="product.uom_code"
                                                        >
                                                            <option value="">Select UOM</option>
                                                            <option
                                                                v-for="uom in uoms"
                                                                :key="uom.id"
                                                                :value="uom.code"
                                                            >
                                                                {{ uom.name }}
                                                            </option>
                                                        </select>
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `po_deposits.${i}.products.${ii}.uom_code`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.uom_code`
                                                                ][0]
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <select
                                                            class="w-full h-40px px-1 text-sm bg-transparent border-none"
                                                            v-model="product.tax_code"
                                                        >
                                                            <option value="">Select Tax</option>
                                                            <option
                                                                v-for="tax in taxes"
                                                                :key="tax.id"
                                                                :value="tax.code"
                                                            >
                                                                {{ tax.name }}
                                                            </option>
                                                        </select>
                                                        <span
                                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                                            v-if="
                                                                state.errors.hasOwnProperty(
                                                                    `po_deposits.${i}.products.${ii}.tax_code`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.tax_code`
                                                                ][0]
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="">
                                                    <div
                                                        class="flex items-center flex-col gap-1"
                                                    >
                                                        <INumber
                                                            class="w-full h-40px px-1 text-sm bg-transparent"
                                                            placeholder="0"
                                                            v-model="
                                                                product.price
                                                            "
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
                                                        <INumber
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
                                                                    `po_deposits.${i}.products.${ii}.price`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.price`
                                                                ][0]
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
                                                        <INumber
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
                                                                    `po_deposits.${i}.products.${ii}.description`
                                                                )
                                                            "
                                                            >{{
                                                                state.errors[
                                                                    `po_deposits.${i}.products.${ii}.description`
                                                                ][0]
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="p-1 input">
                                                    <button
                                                        type="button"
                                                        v-if="
                                                            group.products
                                                                .length > 1 &&
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
                                                <td colspan="9">
                                                    <div class="flex border-t">
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
                                                                            uom_code: null,
                                                                            tax_code: null,
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
                        </div>
                        <div
                            class="w-full bg-white rounded-lg shadow p-8 proform mb-6"
                            v-if="
                                state.products_group.filter(
                                    (e) =>
                                        e.client_po_number ==
                                        group.client_po_number
                                ).length
                            "
                        >
                            <div class="flex justify-between items-center">
                                <div class="text-left flex items-center">
                                    <span class="text-lg"> Actual </span>
                                </div>
                            </div>
                            <template
                                v-for="(
                                    group1, i
                                ) in state.products_group.filter(
                                    (e) =>
                                        e.client_po_number ==
                                        group.client_po_number
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
                                                        colspan="9"
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
                                                                        Delivery
                                                                        Job
                                                                        Number
                                                                    </th>
                                                                    <th
                                                                        class="p-1 w-1/4 font-medium text-left"
                                                                    >
                                                                        Project Type
                                                                    </th>
                                                                    <th
                                                                        class="p-1 w-1/4 font-medium text-left"
                                                                    >
                                                                        Subtotal
                                                                    </th>
                                                                    <td
                                                                        colspan="2"
                                                                    >
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
                                                                    <td
                                                                        class="p-1"
                                                                    >
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
                                                                    <td
                                                                        class="p-1"
                                                                    >
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
                                                                            readonly
                                                                        />
                                                                    </td>
                                                                    <td
                                                                        class="p-1"
                                                                    >
                                                                        <div
                                                                            class="w-full h-30px bg-white border rounded px-2 flex items-center"
                                                                        >
                                                                            <!-- <span
                                                                                class="text-14px text-gray-500 font-semibold"
                                                                                >{{
                                                                                    group1.sent_to_del_at
                                                                                }}</span
                                                                            > -->
                                                                            <select v-model="group1.project_type" class="w-full h-30px bg-transparent">
                                                                                <option v-for="i in ['gimmick','design','print','payment']" :value="i" >{{ i == 'payment' ? 'Supplier Payment' : i.charAt(0).toUpperCase() + i.slice(1) }}</option>
                                                                            </select>   
                                                                        </div>
                                                                    </td>
                                                                    <td
                                                                        class="p-1"
                                                                    >
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
                                                        class="text-left text-sm px-1 py-2 w-1/6"
                                                    >
                                                        Product Code
                                                    </th>
                                                    <th
                                                        class="text-left text-sm px-1 py-2 w-1/6"
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
                                                        UOM
                                                    </th>
                                                    <th
                                                        class="text-left text-sm px-1 py-2 w-120px"
                                                    >
                                                        Tax
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
                                                    <!-- <th
                                                        class="text-left text-sm px-1 py-2 w-80px"
                                                    >
                                                        Production
                                                    </th> -->
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
                                                            <SelectProduct
                                                                :value="product.product_code"
                                                                @select="(data) => onProductSelect(product, data)"
                                                                :ppnType="state.ppn_type"
                                                                :disabled="group1.manufacture != null"
                                                                required
                                                            />
                                                            <span
                                                                class="text-xs text-red-500 text-left w-full block pl-4"
                                                                v-if="
                                                                    state.errors.hasOwnProperty(
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.product_code`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.product_code`
                                                                    ][0]
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
                                                                class="w-full h-40px px-1 text-sm bg-transparent border-none rounded-lg"
                                                                placeholder="Item name"
                                                                v-model="product.name"
                                                            />
                                                            <span
                                                                class="text-xs text-red-500 text-left w-full block pl-4"
                                                                v-if="
                                                                    state.errors.hasOwnProperty(
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.name`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.name`
                                                                    ][0]
                                                                }}</span
                                                            >
                                                        </div>
                                                    </td>

                                                    <td class="">
                                                        <div
                                                            class="flex items-center flex-col gap-1"
                                                        >
                                                            <INumber
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
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.quantity`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.quantity`
                                                                    ][0]
                                                                }}</span
                                                            >
                                                        </div>
                                                    </td>
                                                    <td class="">
                                                        <div
                                                            class="flex items-center flex-col gap-1"
                                                        >
                                                            <select
                                                                class="w-full h-40px px-1 text-sm bg-transparent border-none"
                                                                v-model="product.uom_code"
                                                                disabled
                                                            >
                                                                <option value="">Select UOM</option>
                                                                <option
                                                                    v-for="uom in uoms"
                                                                    :key="uom.id"
                                                                    :value="uom.code"
                                                                >
                                                                    {{ uom.name }}
                                                                </option>
                                                            </select>
                                                            <span
                                                                class="text-xs text-red-500 text-left w-full block pl-4"
                                                                v-if="
                                                                    state.errors.hasOwnProperty(
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.uom_code`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.uom_code`
                                                                    ][0]
                                                                }}</span
                                                            >
                                                        </div>
                                                    </td>
                                                    <td class="">
                                                        <div
                                                            class="flex items-center flex-col gap-1"
                                                        >
                                                            <select
                                                                class="w-full h-40px px-1 text-sm bg-transparent border-none"
                                                                v-model="product.tax_code"
                                                                disabled
                                                            >
                                                                <option value="">Select Tax</option>
                                                                <option
                                                                    v-for="tax in taxes"
                                                                    :key="tax.id"
                                                                    :value="tax.code"
                                                                >
                                                                    {{ tax.name }}
                                                                </option>
                                                            </select>
                                                            <span
                                                                class="text-xs text-red-500 text-left w-full block pl-4"
                                                                v-if="
                                                                    state.errors.hasOwnProperty(
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.tax_code`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.tax_code`
                                                                    ][0]
                                                                }}</span
                                                            >
                                                        </div>
                                                    </td>
                                                    <td class="">
                                                        <div
                                                            class="flex items-center flex-col gap-1"
                                                        >
                                                            <INumber
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
                                                            <INumber
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
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.price`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.price`
                                                                    ][0]
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
                                                            <INumber
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
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.description`
                                                                    )
                                                                "
                                                                >{{
                                                                    state.errors[
                                                                        `products_group.${state.products_group.findIndex(e => e.job_number == group1.job_number)}.products.${ii}.description`
                                                                    ][0]
                                                                }}</span
                                                            >
                                                        </div>
                                                    </td>
                                                    <!-- <td class="p-1 input">
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
                                                    </td> -->
                                                    <td class="p-1 input">
                                                        <button
                                                            type="button"
                                                            v-if="
                                                                group1.products
                                                                    .length >
                                                                    1 &&
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
                                                        user.position !==
                                                            'finance'
                                                    "
                                                >
                                                    <td colspan="9">
                                                        <div
                                                            class="flex border-t"
                                                        >
                                                            
                                                            <button
                                                                type="button"
                                                                class="flex-1 text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full hidon"
                                                                @click="
                                                                    () =>
                                                                        group1.products.push(
                                                                            {
                                                                                name: '',
                                                                                product_code: null,
                                                                                quantity:
                                                                                    '',
                                                                                description:
                                                                                    '',
                                                                                price: null,
                                                                                total_price:
                                                                                    null,
                                                                                is_production: true,
                                                                                uom_code: null,
                                                                                tax_code: null,
                                                                            }
                                                                        )
                                                                "
                                                            >
                                                                <i
                                                                    class="ri-add-box-line text-xl"
                                                                ></i>
                                                                <span>
                                                                    Add More
                                                                    Item
                                                                </span>
                                                            </button>
                                                            <!-- <button
                                                                @click="
                                                                    () =>
                                                                        sendToDel(
                                                                            i
                                                                        )
                                                                "
                                                                type="button"
                                                                class=" text-sm px-4 py-2 bg-red-500 text-white hover:bg-red-600 items-center justify-center flex gap-4"
                                                                v-if="group1.id"
                                                                >
                                                                <i
                                                                    class="ri-share-forward-2-line text-xl"
                                                                ></i>
                                                                <strong
                                                                    >Send to
                                                                    Delivery</strong
                                                                >
                                                            </button> -->
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
                </template>
            </div>

            <div
                class="fixed bottom-0  bg-white border-t border-gray-200 py-4 px-10 flex justify-between items-center transition-all duration-200"
                :class="layoutData?.menu_open ? 'left-279px w-[calc(100vw-279px)]' : 'left-0 w-full'"
            >
                <table v-if="user.position !== 'delivery'">
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
                <div class="flex justify-end gap-4">
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
