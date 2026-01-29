<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    nom,
    goto,
    createPodeposit,
    updatePodeposit,
    getPodeposit,
    APIURL,
    getUomList,
    getTaxList
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
import Select1 from "../components/Select1.vue";
import INumber from "../components/INumber.vue";
import { computed, watch, nextTick } from "vue";
const confirmDelete = ref(false);
const confirmSave = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    on_process: false,
    selected: null,
    selected_type: null,
    clients: [],
    errors: {},
    id: null,
    title: null,
    job_number: null,
    client_po_date: null,
    pic_name: null,
    total_price: null,
    client_po_number: null,
    client_company: null,
    client_code: null,
    client_pic_name: null,
    is_po_deposit: false,
    status: "new",
    products: [{ name: "", quantity: "", description: "" }],
    tab: 0,
    prd: {
        gimmick: null,
        design: null,
        printing: null,
        payment: null,
    },
    documents: {
        do: false,
        bast: false,
        gr: false,
    },
    manual_total_price: false, // Flag to indicate if user wants to manually enter total price
    back: null,
});

// Reactive variables for UOM and Tax options
const uoms = ref([]);
const taxes = ref([]);

// Computed property to calculate total price based on all products
const calculatedTotalPrice = computed(() => {
    let total = 0;

    // Calculate total for gimmick, design, and printing products (quantity * price)
    const nonPaymentProducts = [
        ...(state.prd.gimmick?.products || []),
        ...(state.prd.design?.products || []),
        ...(state.prd.printing?.products || [])
    ];

    nonPaymentProducts.forEach(product => {
        if (product.name !== "" && product.quantity && product.price) {
            total += parseInt(product.quantity) * parseInt(product.price);
        }
    });

    // Calculate total for payment products (price as amount directly)
    const paymentProducts = state.prd.payment?.products || [];
    paymentProducts.forEach(product => {
        if (product.name !== "" && product.price) {
            total += parseInt(product.price);
        }
    });

    return total;
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

const deleteSelectedProduct = () => {
    confirmDelete.value = false;
    state.prd[state.selected_type].products.splice(state.selected, 1);
};

// Track if the focus event should trigger manual mode
const shouldSwitchToManual = ref(true);

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

const onTotalPriceBlur = () => {
    // On blur, validate the input and ensure it's a number
    if (state.total_price !== null && state.total_price !== undefined && state.total_price !== "") {
        state.total_price = parseFloat(state.total_price) || 0;
    }
};
const setDelete = (type, i) => {
    state.selected = i;
    state.selected_type = type;
    confirmDelete.value = true;
};
const submit = () => {
    loading();

    // Calculate total price based on all product subtotals
    const calculateTotalPrice = (products, isPaymentSection = false) => {
        return products.reduce((sum, product) => {
            if (product.name !== "") {
                if (isPaymentSection) {
                    // For payment section, use price directly as the amount
                    return sum + (product.price ? parseInt(product.price) : 0);
                } else {
                    // For other sections, multiply quantity by price
                    if (product.quantity && product.price) {
                        return sum + (parseInt(product.quantity) * parseInt(product.price));
                    }
                }
            }
            return sum;
        }, 0);
    };

    let i = 1;
    const products = [
        {
            ...state.prd.gimmick,
            title: state.title,
            job_number:
                state.job_number + "-" + (i++).toString().padStart(3, "0"),
            client_po_date: state.client_po_date,
            client_po_number: state.client_po_number,
            client_company: state.client_company,
            client_pic_name: state.client_pic_name,
            total_price: calculateTotalPrice(state.prd.gimmick?.products || []),
            project_type: "gimmick",
            documents: state.documents,
        },
        {
            ...state.prd.design,
            title: state.title,
            job_number:
                state.job_number + "-" + (i++).toString().padStart(3, "0"),
            client_po_date: state.client_po_date,
            client_po_number: state.client_po_number,
            client_company: state.client_company,
            client_pic_name: state.client_pic_name,
            total_price: calculateTotalPrice(state.prd.design?.products || []),
            project_type: "design",
            documents: state.documents,
        },
        {
            ...state.prd.printing,
            title: state.title,
            job_number:
                state.job_number + "-" + (i++).toString().padStart(3, "0"),
            client_po_date: state.client_po_date,
            client_po_number: state.client_po_number,
            client_company: state.client_company,
            client_pic_name: state.client_pic_name,
            total_price: calculateTotalPrice(state.prd.printing?.products || []),
            project_type: "printing",
            documents: state.documents,
        },
        {
            ...state.prd.payment,
            title: state.title,
            job_number:
                state.job_number + "-" + (i++).toString().padStart(3, "0"),
            client_po_date: state.client_po_date,
            client_po_number: state.client_po_number,
            client_company: state.client_company,
            client_pic_name: state.client_pic_name,
            total_price: calculateTotalPrice(state.prd.payment?.products || [], true),
            project_type: "payment",
            documents: state.documents,
        },
    ];

    // Process products to include UOM and Tax codes
    products.forEach(productGroup => {
        if (productGroup.products) {
            productGroup.products = productGroup.products.map(product => {
                // Only include product if it has a name
                if (product.name && product.name.trim() !== '') {
                    return {
                        ...product,
                        // Include UOM and Tax codes if they exist
                        uom_code: product.uom_code || null,
                        tax_code: product.tax_code || null
                    };
                }
                return product;
            }).filter(product => product.name && product.name.trim() !== '');
        }
    });

    // Calculate overall total price
    const overallTotalPrice = products.reduce((sum, productGroup) => {
        return sum + (productGroup.total_price || 0);
    }, 0);

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

    // console.log(products)
    // return
    let data = {
        title: state.title,
        total_price: finalTotalPrice, // Use manual input or calculated value
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_code: state.client_code,
        client_pic_name: state.client_pic_name,
        status: state.status,
        products_group: products.filter(
            (e) => e.products.filter((p) => p.name !== "").length > 0
        ),
        is_po_deposit: false,
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
    // console.log(data);
    // if (state.id === null) {
    //     createProject(data).then((r) => {
    //         loading(false);
    //         if (r.code === 200) {
    //             alertShowSuccess.value = true;
    //         } else {
    //             state.errors = r.errors;
    //             alertShowFailed.value = true;
    //         }
    //     });
    // } else {
    //     updateProject(state.id, data).then((r) => {
    //         loading(false);
    //         if (r.code === 200) {
    //             alertShowSuccess.value = true;
    //         } else {
    //             state.errors = r.errors;
    //             alertShowFailed.value = true;
    //         }
    //     });
    // }
};
const generateProds = () => {
    state.prd.gimmick = {
        products: [{ name: "", quantity: "", uom_code: null, tax_code: null, price: "", description: "" }],
    };
    state.prd.design = {
        products: [{ name: "", quantity: "", uom_code: null, tax_code: null, price: "", description: "" }],
    };
    state.prd.printing = {
        products: [{ name: "", quantity: "", uom_code: null, tax_code: null, price: "", description: "" }],
    };
    state.prd.payment = {
        products: [{ name: "", uom_code: null, tax_code: null, price: "", description: "" }],
    };
};
onMounted(() => {
    state.pic_name = currentUser.user.user.name;
    state.back = route.query.back;

    // Load UOM and Tax data from accounting API
    loadUomAndTaxData();

    if (currentUrl.includes("edit")) {
        loading();
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
                state.title = data.title;
                state.total_price = data.total_price;
                if (data.closed_at) {
                    state.closed_at = dayjs(data.closed_at).format(
                        "YYYY-MM-DD"
                    );
                } else {
                    state.closed_at = null;
                }
                const projects = data.projects_data.map((e) => {
                    // Initialize products with price and total_price properties if they don't exist
                    const productsWithPrice = e.products_data.map(product => {
                        // For payment products, use price field as the amount (with fallback to quantity for existing data)
                        if (e.project_type === 'payment') {
                            // Check if we have price field (new format) or need to use quantity (old format)
                            const amountValue = product.price ? parseInt(product.price) :
                                              product.quantity ? parseInt(product.quantity) : 0;
                            return {
                                ...product,
                                // Map ID fields to code fields if they exist
                                uom_code: product.uom_code || (product.unit_id ? product.unit_id : null),
                                tax_code: product.tax_code || (product.tax_id ? product.tax_id : null),
                                price: product.price || product.quantity || "", // Use price if available, otherwise quantity for old data
                                total_price: product.total_price || amountValue
                            };
                        } else {
                            return {
                                ...product,
                                // Map ID fields to code fields if they exist
                                uom_code: product.uom_code || (product.unit_id ? product.unit_id : null),
                                tax_code: product.tax_code || (product.tax_id ? product.tax_id : null),
                                price: product.price || "",
                                total_price: product.total_price || (product.quantity && product.price ? parseInt(product.quantity) * parseInt(product.price) : 0)
                            };
                        }
                    });

                    return {
                        ...e,
                        products: productsWithPrice,
                    };
                });
                state.documents = JSON.parse(projects[0].documents);
                // console.log(state.documents)
                generateProds();
                const gimmick = projects.find(
                    (e) => e.project_type === "gimmick"
                );
                const design = projects.find(
                    (e) => e.project_type === "design"
                );
                const printing = projects.find(
                    (e) => e.project_type === "printing"
                );
                const payment = projects.find(
                    (e) => e.project_type === "payment"
                );
                if (gimmick) {
                    state.prd.gimmick = gimmick;
                }
                if (design) {
                    state.prd.design = design;
                }
                if (printing) {
                    state.prd.printing = printing;
                }
                if (payment) {
                    state.prd.payment = payment;
                }
                console.log(state.prd);
            }
        });
    } else {
        generateProds();
    }
});

// Function to load UOM and Tax data from accounting API
async function loadUomAndTaxData() {
    try {
        // Fetch UOM data using service function (uses default company ID)
        const uomResult = await getUomList();
        if (uomResult.code === 200) {
            uoms.value = uomResult.data;
        }

        // Fetch Tax data using service function (uses default company ID)
        const taxResult = await getTaxList();
        if (taxResult.code === 200) {
            taxes.value = taxResult.data;
        }
    } catch (error) {
        console.error('Error loading UOM and Tax data:', error);
    }
}
</script>

<template>
    <div class="p-8">
        <div
            class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center"
        >
            <div
                class="cursor-pointer mr-4"
                @click="goto(state.back + '?filter=true')"
            >
                <i class="ri-arrow-left-line"></i>
            </div>
            {{ currentUrl.includes("add") ? "Create" : "Update" }}
            <span
                class="rounded-full px-2 py-1 text-11px font-medium text-white whitespace-nowrap ml-2"
                v-if="state.id"
                :class="
                    state.status === 'delivered'
                        ? 'bg-[#6FD669]'
                        : state.status === 'partial'
                        ? 'bg-[#4DC8E3]'
                        : state.status === 'ready'
                        ? 'bg-[#E5C100]'
                        : state.status === 'cancel'
                        ? 'bg-[#E44545]'
                        : state?.status === 'production'
                        ? 'bg-[#3f51b5]'
                        : ''
                "
                >{{
                    state.status === "delivered"
                        ? "Delivered"
                        : state.status === "partial"
                        ? "Partial Delivery"
                        : state.status === "ready"
                        ? "Ready to deliver"
                        : state.status === "cancel"
                        ? "Cancel"
                        : state.status === "production"
                        ? "On Production"
                        : ""
                }}</span
            >
            <div
                class="flex items-start justify-center p-1 flex-col whitespace-nowrap ml-2"
            >
                <span
                    class="text-2xl"
                    v-if="state.id"
                    :class="
                        state.is_po_deposit ? 'text-green-600' : 'text-gray-400'
                    "
                >
                    <i class="ri-money-dollar-circle-fill"></i>
                </span>
            </div>
            <!-- <span class="p-2 rounded bg-red-500 text-white text-xs ml-2" v-show="state.manufacture !== null && currentUrl.includes('edit')
                ">Product Is Readonly</span> -->
        </div>
        <form
            @submit.prevent="confirmSave = true"
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
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
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
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Title</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.title"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-briefcase-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('title')"
                                >{{ state.errors?.title[0] }}</span
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

                    <label class="flex flex-col gap-1 mb-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-left">Omzet</span>
                            <span class="text-blue-500 text-sm cursor-pointer" @click.stop="toggleManualTotalPrice">
                                {{ state.manual_total_price ? 'Auto' : 'Manual' }}
                            </span>
                        </div>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <INumber
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.total_price"
                                :readonly="!state.manual_total_price"
                                @blur="onTotalPriceBlur"
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-money-dollar-box-line"></i>
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


                    <label class="flex flex-col gap-1 mb-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-left">GR/TBP</span>
                        </div>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center px-4 gap-4"
                        >
                            <input
                                type="checkbox"
                                :value="true"
                                v-model="state.documents.gr"
                            />
                            <span>
                                Require to upload GR file
                            </span>
                        </div>
                    </label>

                    
                </div>
                <div class="w-1/2">
                    <label class="flex flex-col gap-1 mb-1">
                        <div class="flex gap-4 items-center">
                            <div
                                class="text-sm text-left leading-4 mb-1 cursor-pointer"
                                :class="
                                    !state.on_process
                                        ? 'text-red-500 font-bold border-b-2 border-red-500'
                                        : ''
                                "
                                @click="
                                    () => {
                                        if (
                                            state.is_po_deposit == false &&
                                            state.status != 'delivered'
                                        ) {
                                            state.on_process = false;
                                        }
                                    }
                                "
                            >
                                PO Number
                            </div>
                            <div
                                class="text-sm text-left leading-4 mb-1 cursor-pointer"
                                :class="
                                    state.on_process
                                        ? 'text-red-500 font-bold border-b-2 border-red-500'
                                        : ''
                                "
                                @click="
                                    () => {
                                        if (
                                            state.is_po_deposit == false &&
                                            state.status != 'delivered'
                                        ) {
                                            state.on_process = true;
                                        }
                                    }
                                "
                            >
                                PO dalam proses
                            </div>
                        </div>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="text"
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.client_po_number"
                                required
                                v-if="!state.on_process"
                            />
                            <div
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px flex items-center"
                                v-else
                            >
                                Dalam proses
                            </div>
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
                    </label>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">PO Date</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <input
                                type="date"
                                class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                v-model="state.client_po_date"
                                onfocus="this.showPicker()"
                                required
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
                    </label>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">Client Company</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <Select1
                                @select="(e) => { state.client_company = e.name; state.client_code = e.code; if(e.contact_person) state.client_pic_name = e.contact_person; }"
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

                    <label class="flex flex-col gap-1 mb-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-left">BAST</span>
                        </div>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center px-4 gap-4"
                        >
                            <input
                                type="checkbox"
                                :value="true"
                                v-model="state.documents.bast"
                            />
                            <span>
                                Require to upload BAST file
                            </span>
                        </div>
                    </label>
                </div>
            </div>
            <div>
                <!-- <div class="flex justify-between">
                    <strong class="block mb-4 text-lg text-left"
                        >Products</strong
                    >
                </div> -->
                <div class="flex gap-8 items-center mb-4 mt-8">
                    <div
                        class="text-sm text-left py-1 cursor-pointer"
                        v-for="(option, index) in [
                            'Gimmick',
                            'Design',
                            'Printing',
                            'Supplier Payment',
                        ]"
                        :key="index"
                        :class="
                            state.tab === index
                                ? 'text-red-500 border-b-2 border-red-500 font-semibold'
                                : 'border-b-2 border-transparent text-gray-500'
                        "
                        @click="
                            () => {
                                state.tab = index;
                            }
                        "
                    >
                        {{ option }}
                        <strong
                            class="text-xs ml-1 px-1 rounded-full w-4 h-4"
                            :class="
                                state.tab === index
                                    ? 'bg-red-200 text-red-500'
                                    : 'bg-gray-200 text-gray-500'
                            "
                        >
                            {{
                                state.prd[
                                    Object.keys(state.prd)[index]
                                ]?.products.filter(
                                    (e) => e.name !== null && e.name !== ""
                                ).length
                            }}
                        </strong>
                    </div>
                </div>

                <div class="border rounded-lg" v-if="state.tab == 0">
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Product Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    UOM
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Tax
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Price
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Subtotal
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Description
                                </th>
                                <th class="w-60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.prd.gimmick
                                    ?.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Product Name"
                                            v-model="product.name"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.uom_code"
                                        >
                                            <option value="">Select UOM</option>
                                            <option
                                                v-for="uom in uoms"
                                                :key="uom.id"
                                                :value="uom.code"
                                            >
                                                {{ uom.name }} ({{ uom.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.uom_code`
                                                ][0].replace(
                                                    `products.${i}.uom_code `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.tax_code"
                                        >
                                            <option value="">Select Tax</option>
                                            <option
                                                v-for="tax in taxes"
                                                :key="tax.id"
                                                :value="tax.code"
                                            >
                                                {{ tax.name }} ({{ tax.tax_percentage }}%) ({{ tax.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.tax_code`
                                                ][0].replace(
                                                    `products.${i}.tax_code `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.price"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.price`
                                                ][0].replace(
                                                    `products.${i}.price `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center justify-center h-full"
                                    >
                                        <div
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center"
                                            placeholder="0"
                                        >
                                            {{ product.quantity && product.price ? nom(product.quantity * product.price) : '0' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Warna/Finishing"
                                            v-model="product.description"
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="p-1">
                                    <button
                                        type="button"
                                        class="text-xl px-4 h-40px text-[#667085] hover:bg-gray-100 rounded"
                                        @click="setDelete('gimmick', i)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.gimmick.products.push(
                                                    {
                                                        name: '',
                                                        quantity: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        price: '',
                                                        description: '',
                                                    }
                                                )
                                        "
                                    >
                                        <i class="ri-add-box-line text-xl"></i>
                                        <span> Add More Item </span>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="border rounded-lg" v-if="state.tab == 1">
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Item Name
                                </th>

                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    UOM
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Tax
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Price
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Subtotal
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Item Specification
                                </th>
                                <th class="w-60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.prd.design
                                    ?.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Item Name"
                                            v-model="product.name"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.uom_code"
                                        >
                                            <option value="">Select UOM</option>
                                            <option
                                                v-for="uom in uoms"
                                                :key="uom.id"
                                                :value="uom.code"
                                            >
                                                {{ uom.name }} ({{ uom.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.uom_code`
                                                ][0].replace(
                                                    `products.${i}.uom_code `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.tax_code"
                                        >
                                            <option value="">Select Tax</option>
                                            <option
                                                v-for="tax in taxes"
                                                :key="tax.id"
                                                :value="tax.code"
                                            >
                                                {{ tax.name }} ({{ tax.tax_percentage }}%) ({{ tax.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.tax_code`
                                                ][0].replace(
                                                    `products.${i}.tax_code `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.price"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.price`
                                                ][0].replace(
                                                    `products.${i}.price `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center justify-center h-full"
                                    >
                                        <div
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center"
                                            placeholder="0"
                                        >
                                            {{ product.quantity && product.price ? nom(product.quantity * product.price) : '0' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Ukuran/sisi"
                                            v-model="product.description"
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="p-1">
                                    <button
                                        type="button"
                                        class="text-xl px-4 h-40px text-[#667085] hover:bg-gray-100 rounded"
                                        @click="setDelete('design', i)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.design.products.push({
                                                    name: '',
                                                    quantity: '',
                                                    uom_code: null,
                                                    tax_code: null,
                                                    price: '',
                                                    description: '',
                                                })
                                        "
                                    >
                                        <i class="ri-add-box-line text-xl"></i>
                                        <span> Add More Item </span>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="border rounded-lg" v-if="state.tab == 2">
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Product Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    UOM
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Tax
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Price
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Subtotal
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Specification
                                </th>
                                <th class="w-60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.prd.printing
                                    ?.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Product Name"
                                            v-model="product.name"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.uom_code"
                                        >
                                            <option value="">Select UOM</option>
                                            <option
                                                v-for="uom in uoms"
                                                :key="uom.id"
                                                :value="uom.code"
                                            >
                                                {{ uom.name }} ({{ uom.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.uom_code`
                                                ][0].replace(
                                                    `products.${i}.uom_code `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.tax_code"
                                        >
                                            <option value="">Select Tax</option>
                                            <option
                                                v-for="tax in taxes"
                                                :key="tax.id"
                                                :value="tax.code"
                                            >
                                                {{ tax.name }} ({{ tax.tax_percentage }}%) ({{ tax.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.tax_code`
                                                ][0].replace(
                                                    `products.${i}.tax_code `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.price"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.price`
                                                ][0].replace(
                                                    `products.${i}.price `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center justify-center h-full"
                                    >
                                        <div
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center"
                                            placeholder="0"
                                        >
                                            {{ product.quantity && product.price ? nom(product.quantity * product.price) : '0' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Ukuran/Sisi/Finishing"
                                            v-model="product.description"
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="p-1">
                                    <button
                                        type="button"
                                        class="text-xl px-4 h-40px text-[#667085] hover:bg-gray-100 rounded"
                                        @click="setDelete('printing', i)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.printing.products.push(
                                                    {
                                                        name: '',
                                                        quantity: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        price: '',
                                                        description: '',
                                                    }
                                                )
                                        "
                                    >
                                        <i class="ri-add-box-line text-xl"></i>
                                        <span> Add More Item </span>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="border rounded-lg" v-if="state.tab == 3">
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Supplier Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Account Number
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    UOM
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Tax
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Amount
                                </th>
                                <th class="w-60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.prd.payment
                                    ?.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="e.g. : PT Astra"
                                            v-model="product.name"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
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
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="e.g. : 83292092 "
                                            v-model="product.description"
                                        />

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.uom_code"
                                        >
                                            <option value="">Select UOM</option>
                                            <option
                                                v-for="uom in uoms"
                                                :key="uom.id"
                                                :value="uom.code"
                                            >
                                                {{ uom.name }} ({{ uom.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.uom_code`
                                                ][0].replace(
                                                    `products.${i}.uom_code `,
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
                                        <select
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none"
                                            v-model="product.tax_code"
                                        >
                                            <option value="">Select Tax</option>
                                            <option
                                                v-for="tax in taxes"
                                                :key="tax.id"
                                                :value="tax.code"
                                            >
                                                {{ tax.name }} ({{ tax.tax_percentage }}%) ({{ tax.code }})
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.tax_code`
                                                ][0].replace(
                                                    `products.${i}.tax_code `,
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
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="e.g. : 255.000 "
                                            v-model="product.price"
                                            required
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.price`
                                                ][0].replace(
                                                    `products.${i}.price `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="p-1">
                                    <button
                                        type="button"
                                        class="text-xl px-4 h-40px text-[#667085] hover:bg-gray-100 rounded"
                                        @click="setDelete('payment', i)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.payment.products.push(
                                                    {
                                                        name: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        price: '',
                                                        description: '',
                                                    }
                                                )
                                        "
                                    >
                                        <i class="ri-add-box-line text-xl"></i>
                                        <span> Add More Item </span>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- <div class="text-sm text-[#667085] mt-12 mb-2">
                    Choose Documents to be Uploaded
                </div>
                <div class="border p-3 flex justify-between rounded-lg mb-30">
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
                </div> -->

                <div class="flex justify-end gap-4 mt-8">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        type="button"
                        @click="router.push(state.back + '?filter=true')"
                    >
                        <i class="ri-close-circle-line text-xl"></i>
                        <strong>Cancel</strong>
                    </button>
                    <button
                        class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                        v-if="state.is_po_deposit == false"
                    >
                        <i class="ri-save-line text-xl"></i>
                        <strong>{{
                            currentUrl.includes("add") ? "Verify" : "Update"
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
        title="Save Confirmation"
        content="Are you sure want to save project?"
        buttonText="Yes, Save"
        :show="confirmSave != false"
        @hide="confirmSave = false"
        @fire="submit"
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
        title="Project Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                router.push(state.back + '?filter=true');
            }
        "
    ></Alert>
</template>
