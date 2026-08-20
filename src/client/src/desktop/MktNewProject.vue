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
import SelectProduct from "../components/SelectProduct.vue";
import INumber from "../components/INumber.vue";
import { computed, watch } from "vue";
const confirmDelete = ref(false);
const confirmSave = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const showTaxAlert = ref(false);
const taxAlertMessage = ref('');
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
    ppn_type: 'ppn', // ppn or non_ppn
    is_po_deposit: false,
    is_bundle_mode: false,
    bundle_groups: [],
    bundle_selected: null,
    status: "new",
    products: [{ name: "", product_code: "", quantity: "", description: "" }],
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
    back: null,
});

// Reactive variables for UOM and Tax options
const uoms = ref([]);
const taxes = ref([]);

// Handle product selection - autofill price, uom, tax, description
const onProductSelect = (product, selectedData) => {
    if (selectedData && selectedData.name) {
        product.name = selectedData.name;
        product.product_code = selectedData.code;
        product.price = selectedData.selling_price || 0;
        product.description = selectedData.description || '';
        // Clear and set UOM code
        product.uom_code = selectedData.unit ? selectedData.unit.code : null;
        
        // For NON-PPN, always set tax to 0% regardless of product's tax
        if (state.ppn_type === 'non_ppn') {
            const zeroTax = taxes.value?.find(tax => parseFloat(tax.tax_percentage) === 0);
            product.tax_code = zeroTax ? zeroTax.code : null;
        } else {
            // For PPN, use product's tax
            product.tax_code = selectedData.tax ? selectedData.tax.code : null;
        }
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

// Helper function to calculate price with tax
// includePpn: if true, the price already includes PPN — return as-is
// includePpn: if false (default), add tax on top of the base price
const calculatePriceWithTax = (price, taxCode, includePpn = false) => {
    if (includePpn) return price; // price already includes PPN, no extra tax added
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

const bundleCalculatedTotal = computed(() => {
    let total = 0;
    (state.bundle_groups || []).forEach(g => {
        if (g.name && g.name.trim() !== '' && g.sets_qty && g.set_price) {
            const priceWithTax = calculatePriceWithTax(parseFloat(g.set_price), g.tax_code, g.include_ppn);
            total += parseFloat(g.sets_qty) * priceWithTax;
        }
    });
    return Math.round(total * 100) / 100;
});

// Computed property to calculate total price based on all products
const calculatedTotalPrice = computed(() => {
    if (state.is_bundle_mode) return bundleCalculatedTotal.value;
    let total = 0;

    // Calculate total for gimmick, design, and printing products (quantity * price with tax)
    const nonPaymentProducts = [
        ...(state.prd.gimmick?.products || []),
        ...(state.prd.design?.products || []),
        ...(state.prd.printing?.products || [])
    ];

    nonPaymentProducts.forEach(product => {
        if (product.name !== "" && product.quantity && product.price) {
            const priceWithTax = calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn);
            total += parseFloat(product.quantity) * priceWithTax;
        }
    });

    // Calculate total for payment products (price as amount directly)
    const paymentProducts = state.prd.payment?.products || [];
    paymentProducts.forEach(product => {
        if (product.name !== "" && product.price) {
            total += parseFloat(product.price);
        }
    });

    // Round to 2 decimal places to avoid floating-point precision issues
    return Math.round(total * 100) / 100;
});

// Watcher to update total_price automatically from calculated value
watch(calculatedTotalPrice, (newVal) => {
    // Always update with the calculated value
    if (state.total_price !== newVal) {
        state.total_price = newVal;
    }
}, { immediate: true });

// Watch for ppn_type changes and reload UOM/Tax data
watch(() => state.ppn_type, async () => {
    await loadUomAndTaxData();
    
    // Check if 0% tax exists for NON-PPN
    if (state.ppn_type === 'non_ppn') {
        const zeroTax = taxes.value?.find(tax => parseFloat(tax.tax_percentage) === 0);
        if (!zeroTax) {
            taxAlertMessage.value = 'Tax with 0% percentage (Non-PPN) is required. Please create a tax with 0% percentage in the Accounting system first.';
            showTaxAlert.value = true;
            // Change back to PPN to prevent creating non-ppn project
            state.ppn_type = 'ppn';
        }
    }
});

const deleteSelectedProduct = () => {
    confirmDelete.value = false;
    if (state.is_bundle_mode && state.bundle_selected) {
        const [gi, ci] = state.bundle_selected;
        if (ci === null || ci === undefined) {
            state.bundle_groups.splice(gi, 1);
        } else {
            state.bundle_groups[gi].components.splice(ci, 1);
        }
        state.bundle_selected = null;
        return;
    }
    state.prd[state.selected_type].products.splice(state.selected, 1);
};
const setDelete = (type, i) => {
    state.selected = i;
    state.selected_type = type;
    confirmDelete.value = true;
};
const onBundleProductSelect = (bundle, selectedData) => {
    if (selectedData && selectedData.name) {
        bundle.name = selectedData.name;
        bundle.product_code = selectedData.code;
        bundle.set_price = selectedData.selling_price || 0;
        bundle.description = selectedData.description || '';
        bundle.uom_code = selectedData.unit ? selectedData.unit.code : null;
        if (state.ppn_type === 'non_ppn') {
            const zeroTax = taxes.value?.find(tax => parseFloat(tax.tax_percentage) === 0);
            bundle.tax_code = zeroTax ? zeroTax.code : null;
        } else {
            bundle.tax_code = selectedData.tax ? selectedData.tax.code : null;
        }
    } else {
        bundle.name = null;
        bundle.product_code = null;
        bundle.set_price = null;
        bundle.description = null;
        bundle.uom_code = null;
        bundle.tax_code = null;
    }
};
const onBundleComponentSelect = (comp, selectedData) => {
    if (selectedData && selectedData.name) {
        comp.name = selectedData.name;
        comp.product_code = selectedData.code;
        comp.price = selectedData.selling_price || 0;
        comp.description = selectedData.description || '';
        comp.uom_code = selectedData.unit ? selectedData.unit.code : null;
        if (state.ppn_type === 'non_ppn') {
            const zeroTax = taxes.value?.find(tax => parseFloat(tax.tax_percentage) === 0);
            comp.tax_code = zeroTax ? zeroTax.code : null;
        } else {
            comp.tax_code = selectedData.tax ? selectedData.tax.code : null;
        }
    } else {
        comp.name = null;
        comp.product_code = null;
        comp.price = null;
        comp.description = null;
        comp.uom_code = null;
        comp.tax_code = null;
    }
};
const addBundleGroup = () => {
    state.bundle_groups.push({
        name: '', product_code: '', sets_qty: '', set_price: '', uom_code: null, tax_code: null, include_ppn: false, description: '',
        components: [{ name: '', product_code: '', qty_per_set: '', quantity: '', uom_code: null, tax_code: null, include_ppn: false, is_production: true, price: '', description: '' }]
    });
};
const addBundleComponent = (gi) => {
    state.bundle_groups[gi].components.push({ name: '', product_code: '', qty_per_set: '', quantity: '', uom_code: null, tax_code: null, include_ppn: false, is_production: true, price: '', description: '' });
};
const setBundleDelete = (gi, ci = null) => {
    state.bundle_selected = [gi, ci];
    confirmDelete.value = true;
};
const recalcBundleQuantities = () => {
    state.bundle_groups.forEach(g => {
        const sets = parseFloat(g.sets_qty) || 0;
        g.components.forEach(c => {
            const qps = parseFloat(c.qty_per_set);
            if (!isNaN(qps) && sets) {
                c.quantity = Math.round(qps * sets * 100) / 100;
            }
        });
    });
};
watch(() => state.bundle_groups.map(g => g.sets_qty), () => { recalcBundleQuantities(); });
watch(() => state.bundle_groups.flatMap(g => g.components.map(c => c.qty_per_set)), () => { recalcBundleQuantities(); });
const submit = () => {
    // Check if NON-PPN is selected but 0% tax doesn't exist
    if (state.ppn_type === 'non_ppn') {
        const zeroTax = taxes.value?.find(tax => parseFloat(tax.tax_percentage) === 0);
        if (!zeroTax) {
            taxAlertMessage.value = 'Tax with 0% percentage (Non-PPN) is required. Please create a tax with 0% percentage in the Accounting system first.';
            showTaxAlert.value = true;
            return;
        }
    }
    
    if (state.is_bundle_mode) {
        state.errors = {};
        let hasError = false;
        if (!state.client_code) { state.errors.client_company = ['Client code is missing. Please re-select the client company.']; hasError = true; }
        if (!state.bundle_groups || state.bundle_groups.length === 0) { state.errors.bundle_groups = ['At least one bundle is required']; hasError = true; }
        state.bundle_groups.forEach((g, gi) => {
            if (!g.name || g.name.trim() === '') { state.errors[`bundle_groups.${gi}.name`] = ['Bundle name is required']; hasError = true; }
            if (!g.sets_qty || parseFloat(g.sets_qty) <= 0) { state.errors[`bundle_groups.${gi}.sets_qty`] = ['Qty (Set) is required']; hasError = true; }
            if (!g.set_price || parseFloat(g.set_price) <= 0) { state.errors[`bundle_groups.${gi}.set_price`] = ['Price / Set is required']; hasError = true; }
            if (!g.product_code) { state.errors[`bundle_groups.${gi}.product_code`] = ['Product Code is required']; hasError = true; }
            if (!g.uom_code) { state.errors[`bundle_groups.${gi}.uom_code`] = ['UOM is required']; hasError = true; }
            if (!g.tax_code) { state.errors[`bundle_groups.${gi}.tax_code`] = ['Tax is required']; hasError = true; }
            if (!g.components || g.components.length === 0) { state.errors[`bundle_groups.${gi}.components`] = ['At least one component is required']; hasError = true; }
            g.components.forEach((c, ci) => {
                if (!c.name || c.name.trim() === '') return;
                const ek = `bundle_groups.${gi}.components.${ci}`;
                if (!c.qty_per_set || parseFloat(c.qty_per_set) <= 0) { state.errors[`${ek}.qty_per_set`] = ['Qty / Set is required']; hasError = true; }
                if (!c.price || parseFloat(c.price) <= 0) { state.errors[`${ek}.price`] = ['Price is required']; hasError = true; }
                if (!c.uom_code) { state.errors[`${ek}.uom_code`] = ['UOM is required']; hasError = true; }
                if (!c.tax_code) { state.errors[`${ek}.tax_code`] = ['Tax is required']; hasError = true; }
                if (!c.product_code) { state.errors[`${ek}.product_code`] = ['Product Code is required']; hasError = true; }
            });
        });
        if (hasError) { alertShowFailed.value = true; return; }
        loading();
        recalcBundleQuantities();
        const po_deposits = state.bundle_groups.map((g, i) => {
            const pw = calculatePriceWithTax(parseFloat(g.set_price), g.tax_code, g.include_ppn);
            const total_price = Math.round(parseFloat(g.sets_qty || 0) * pw * 100) / 100;
            return { title: g.name, job_number: state.job_number + "-D" + (i+1), client_po_date: state.client_po_date, client_po_number: 'BUNDLE-' + String(i+1).padStart(3,'0'), client_company: state.client_company, client_pic_name: state.client_pic_name, total_price, project_type: 'gimmick', documents: state.documents, products: [{ name: g.name, product_code: g.product_code, quantity: g.sets_qty, qty_per_set: null, is_group_main: true, price: g.set_price, total_price, uom_code: g.uom_code, tax_code: g.tax_code, include_ppn: g.include_ppn || false, is_production: false, description: g.description || '' }] };
        });
        const products_group = state.bundle_groups.map((g, i) => {
            const comps = g.components.filter(c => c.name && c.name.trim() !== '').map(c => { const qty = parseFloat(c.quantity) || Math.round(parseFloat(c.qty_per_set||0) * parseFloat(g.sets_qty||0) * 100)/100; const pw = calculatePriceWithTax(parseFloat(c.price), c.tax_code, c.include_ppn); const total_price = Math.round(qty * pw * 100)/100; return { name: c.name, product_code: c.product_code, quantity: qty, qty_per_set: c.qty_per_set, is_group_main: false, price: c.price, total_price, uom_code: c.uom_code, tax_code: c.tax_code, include_ppn: c.include_ppn || false, is_production: c.is_production !== undefined ? c.is_production : true, description: c.description || '' }; });
            const total_price = Math.round(comps.reduce((a,b)=>a+(b.total_price||0),0)*100)/100; return { title: g.name, job_number: state.job_number + "-A" + (i+1), client_po_date: state.client_po_date, client_po_number: 'BUNDLE-' + String(i+1).padStart(3,'0'), client_company: state.client_company, client_pic_name: state.client_pic_name, total_price, project_type: 'gimmick', documents: state.documents, products: comps };
        });
        let data = { title: state.title, total_price: bundleCalculatedTotal.value, job_number: state.job_number, client_po_date: state.client_po_date, pic_name: state.pic_name, client_po_number: state.client_po_number, client_company: state.client_company, client_code: state.client_code, client_pic_name: state.client_pic_name, ppn_type: state.ppn_type, status: state.status, po_deposits, products_group, is_po_deposit: true, is_bundle: true, documents: state.documents };
        if (state.id === null) { createPodeposit(data).then(r=>{ loading(false); if(r.code===200) alertShowSuccess.value=true; else {state.errors=r.errors; alertShowFailed.value=true;}}); } else { updatePodeposit(state.id, data).then(r=>{ loading(false); if(r.code===200) alertShowSuccess.value=true; else {state.errors=r.errors; alertShowFailed.value=true;}}); }
        return;
    }
    // Validate products before submitting
    const validateProducts = () => {
        // Reset errors before validation
        state.errors = {};
        let hasError = false;

        // Validate client_code (must re-select company if code is empty)
        if (!state.client_code) {
            state.errors.client_company = ['Client code is missing. Please re-select the client company.'];
            hasError = true;
        }

        const productGroups = [
            { name: 'gimmick', products: state.prd.gimmick?.products || [] },
            { name: 'design', products: state.prd.design?.products || [] },
            { name: 'printing', products: state.prd.printing?.products || [] },
            { name: 'payment', products: state.prd.payment?.products || [] }
        ];

        // Build index mapping for each product
        const productIndices = {};
        productGroups.forEach(group => {
            group.products.forEach((product, index) => {
                productIndices[product] = { group: group.name, index: index };
            });
        });

        productGroups.forEach(group => {
            group.products.forEach((product, i) => {
                if (product.name && product.name.trim() !== '') {
                    const errorKey = `${group.name}.products.${i}`;

                    // For payment products, only price is required
                    if (group.name === 'payment') {
                        if (!product.price || product.price <= 0) {
                            state.errors[`${errorKey}.price`] = ['Price is required and must be greater than 0'];
                            hasError = true;
                        }
                    } else {
                        // For other products, quantity, price, UOM, and tax are required
                        if (!product.quantity || product.quantity <= 0) {
                            state.errors[`${errorKey}.quantity`] = ['Quantity is required and must be greater than 0'];
                            hasError = true;
                        }

                        if (!product.price || product.price <= 0) {
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

                        if (!product.product_code) {
                            state.errors[`${errorKey}.product_code`] = ['Product Code is required'];
                            hasError = true;
                        }
                    }
                }
            });
        });

        if (hasError) {
            alertShowFailed.value = true;
            return false;
        }

        return true;
    };

    if (!validateProducts()) {
        return;
    }

    loading();

    // Calculate total price based on all product subtotals
    const calculateTotalPrice = (products, isPaymentSection = false) => {
        const total = products.reduce((sum, product) => {
            if (product.name !== "") {
                if (isPaymentSection) {
                    // For payment section, use price directly as the amount
                    return sum + (product.price ? parseFloat(product.price) : 0);
                } else {
                    // For other sections, multiply quantity by price with tax
                    if (product.quantity && product.price) {
                        const priceWithTax = calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn);
                        return sum + (parseFloat(product.quantity) * priceWithTax);
                    }
                }
            }
            return sum;
        }, 0);
        // Round to 2 decimal places to avoid floating-point precision issues
        return Math.round(total * 100) / 100;
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
                        tax_code: product.tax_code || null,
                        // Use user selected is_production value, default to true for production types
                        is_production: product.is_production !== undefined ? product.is_production : !['design', 'payment', 'supplier payment'].includes(productGroup.project_type),
                    };
                }
                return product;
            }).filter(product => product.name && product.name.trim() !== '');
        }
    });

    // Calculate overall total price
    const overallTotalPrice = Math.round(products.reduce((sum, productGroup) => {
        return sum + (productGroup.total_price || 0);
    }, 0) * 100) / 100;

    // Always use the calculated value
    let finalTotalPrice = calculatedTotalPrice.value;

    // console.log(products)
    // return
    let data = {
        title: state.title,
        total_price: finalTotalPrice, // Always use calculated value
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_code: state.client_code,
        client_pic_name: state.client_pic_name,
        ppn_type: state.ppn_type,
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
        products: [{ name: "", product_code: "", quantity: "", uom_code: null, tax_code: null, include_ppn: false, is_production: true, price: "", description: "" }],
    };
    state.prd.design = {
        products: [{ name: "", product_code: "", quantity: "", uom_code: null, tax_code: null, include_ppn: false, is_production: false, price: "", description: "" }],
    };
    state.prd.printing = {
        products: [{ name: "", product_code: "", quantity: "", uom_code: null, tax_code: null, include_ppn: false, is_production: true, price: "", description: "" }],
    };
    state.prd.payment = {
        products: [{ name: "", product_code: "", uom_code: null, tax_code: null, is_production: false, price: "", description: "" }],
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
                state.ppn_type = data.ppn_type || 'ppn';
                state.title = data.title;
                state.total_price = data.total_price;
                if (data.closed_at) {
                    state.closed_at = dayjs(data.closed_at).format(
                        "YYYY-MM-DD"
                    );
                } else {
                    state.closed_at = null;
                }
                if (data.is_bundle) {
                    state.is_bundle_mode = true;
                    const deposits = data.projects_data.filter(e => !e.is_real);
                    const actuals = data.projects_data.filter(e => e.is_real);
                    const map = new Map();
                    deposits.forEach(d => {
                        const main = (d.products_data && d.products_data[0]) || {};
                        map.set(d.client_po_number, { name: main.name || d.title || '', product_code: main.product_code || '', sets_qty: main.quantity || '', set_price: main.price || '', uom_code: main.uom_code || null, tax_code: main.tax_code || null, include_ppn: main.include_ppn || false, description: main.description || '', components: [] });
                    });
                    actuals.forEach(a => {
                        const key = a.client_po_number;
                        const entry = map.get(key);
                        if (!entry) return;
                        (a.products_data || []).forEach(p => {
                            entry.components.push({ name: p.name || '', product_code: p.product_code || '', qty_per_set: p.qty_per_set ?? '', quantity: p.quantity || '', uom_code: p.uom_code || null, tax_code: p.tax_code || null, include_ppn: p.include_ppn || false, is_production: p.is_production !== undefined ? (p.is_production==1 || p.is_production===true) : true, price: p.price || '', total_price: p.total_price || 0, description: p.description || '' });
                        });
                    });
                    state.bundle_groups = Array.from(map.values());
                    if (state.bundle_groups.length === 0) state.bundle_groups = [{ name: '', product_code: '', sets_qty: '', set_price: '', uom_code: null, tax_code: null, include_ppn: false, description: '', components: [{ name: '', product_code: '', qty_per_set: '', quantity: '', uom_code: null, tax_code: null, include_ppn: false, is_production: true, price: '', description: '' }] }];
                    const firstDoc = deposits[0] || actuals[0];
                    try { state.documents = JSON.parse(firstDoc.documents); } catch(e) { state.documents = {do:false,bast:false,gr:false}; }
                    generateProds();
                    console.log(state.bundle_groups);
                    return;
                }
                const projects = data.projects_data.map((e) => {
                    // Initialize products with price and total_price properties if they don't exist
                    const productsWithPrice = e.products_data.map(product => {
                        // For payment products, use price field as the amount (with fallback to quantity for existing data)
                        if (e.project_type === 'payment') {
                            // Check if we have price field (new format) or need to use quantity (old format)
                            const amountValue = product.price ? parseFloat(product.price) :
                                              product.quantity ? parseFloat(product.quantity) : 0;
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
                                include_ppn: product.include_ppn || false,
                                is_production: product.is_production !== undefined ? (product.is_production == 1 || product.is_production === true) : !['design', 'payment', 'supplier payment'].includes(e.project_type),
                                price: product.price || "",
                                total_price: product.total_price || (product.quantity && product.price ? parseFloat(product.quantity) * calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn || false) : 0)
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
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.job_number"
                                :disabled="currentUrl.includes('edit')"
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
                        <span class="text-sm text-left">Omzet (Auto-calculated)</span>
                        <div
                            class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                        >
                            <INumber
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                v-model="state.total_price"
                                readonly
                            />
                            <div class="absolute left-4 text-red-500 text-xl">
                                <i class="ri-money-dollar-box-line"></i>
                            </div>
                        </div>
                        <div class="h-3 flex -mt-1">
                            <span
                                class="text-xs text-red-500"
                                v-if="state.errors.hasOwnProperty('total_price')"
                                >{{ state.errors?.total_price[0] }}</span
                            >
                        </div>
                    </label>


                    <div class="h-3 flex -mt-1"><!--v-if--></div>
                    <label class="flex flex-col gap-1 mb-1">
                        <span class="text-sm text-left">BAST</span>
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
                                :ppnType="state.ppn_type"
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
            </div>
            <div style="margin-top:16px;padding:12px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" :checked="state.is_bundle_mode" @change="state.is_bundle_mode = $event.target.checked" style="width:18px;height:18px;accent-color:#ef4444">
                    <b style="font-size:13px">Mode Bundle/Set</b>
                </label>
                <span class="text-xs" :style="{background: state.is_bundle_mode ? '#fef3c7' : '#e5e7eb', padding: '2px 8px', borderRadius: '9999px'}">{{ state.is_bundle_mode ? 'ON — Bundle' : 'OFF — Normal' }}</span>
            </div>
            <div v-if="!state.is_bundle_mode">
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
                    <table class="w-full rounded-lg" style="overflow: visible;">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Product Code
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/6">
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
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Incl. PPN
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Prod.
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
                                        <SelectProduct
                                            @select="(e) => onProductSelect(product, e)"
                                            :value="product.product_code"
                                            :ppnType="state.ppn_type"
                                            required="true"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `gimmick.products.${i}.product_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.product_code`
                                                ][0].replace(
                                                    `gimmick.products.${i}.product_code `,
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
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none rounded-lg"
                                            placeholder="Item name"
                                            v-model="product.name"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `gimmick.products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.name`
                                                ][0].replace(
                                                    `gimmick.products.${i}.name `,
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
                                                    `gimmick.products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.quantity`
                                                ][0].replace(
                                                    `gimmick.products.${i}.quantity `,
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
                                                {{ uom.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `gimmick.products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.uom_code`
                                                ][0].replace(
                                                    `gimmick.products.${i}.uom_code `,
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
                                                {{ tax.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `gimmick.products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.tax_code`
                                                ][0].replace(
                                                    `gimmick.products.${i}.tax_code `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.include_ppn"
                                                class="w-4 h-4 accent-red-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.include_ppn ? 'text-red-500 font-semibold' : 'text-gray-400'">
                                                {{ product.include_ppn ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.is_production"
                                                class="w-4 h-4 accent-blue-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.is_production ? 'text-blue-500 font-semibold' : 'text-gray-400'">
                                                {{ product.is_production ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
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
                                                    `gimmick.products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.price`
                                                ][0].replace(
                                                    `gimmick.products.${i}.price `,
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
                                            {{ product.quantity && product.price ? nom(Math.round(parseFloat(product.quantity) * calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn) * 100) / 100) : '0' }}
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
                                                    `gimmick.products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `gimmick.products.${i}.description`
                                                ][0].replace(
                                                    `gimmick.products.${i}.description `,
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
                                <td colspan="11">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.gimmick.products.push(
                                                    {
                                                        name: '',
                                                        product_code: '',
                                                        quantity: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        include_ppn: false,
                                                        is_production: true,
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
                    <table class="w-full rounded-lg" style="overflow: visible;">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Product Code
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/6">
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
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Incl. PPN
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Prod.
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
                                        <SelectProduct
                                            @select="(e) => onProductSelect(product, e)"
                                            :value="product.product_code"
                                            :ppnType="state.ppn_type"
                                            required="true"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `design.products.${i}.product_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.product_code`
                                                ][0].replace(
                                                    `design.products.${i}.product_code `,
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
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none rounded-lg"
                                            placeholder="Item name"
                                            v-model="product.name"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `design.products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.name`
                                                ][0].replace(
                                                    `design.products.${i}.name `,
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
                                                    `design.products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.quantity`
                                                ][0].replace(
                                                    `design.products.${i}.quantity `,
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
                                                {{ uom.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `design.products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.uom_code`
                                                ][0].replace(
                                                    `design.products.${i}.uom_code `,
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
                                                {{ tax.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `design.products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.tax_code`
                                                ][0].replace(
                                                    `design.products.${i}.tax_code `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.include_ppn"
                                                class="w-4 h-4 accent-red-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.include_ppn ? 'text-red-500 font-semibold' : 'text-gray-400'">
                                                {{ product.include_ppn ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.is_production"
                                                class="w-4 h-4 accent-blue-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.is_production ? 'text-blue-500 font-semibold' : 'text-gray-400'">
                                                {{ product.is_production ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
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
                                                    `design.products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.price`
                                                ][0].replace(
                                                    `design.products.${i}.price `,
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
                                            {{ product.quantity && product.price ? nom(Math.round(parseFloat(product.quantity) * calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn) * 100) / 100) : '0' }}
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
                                                    `design.products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `design.products.${i}.description`
                                                ][0].replace(
                                                    `design.products.${i}.description `,
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
                                <td colspan="11">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.design.products.push({
                                                    name: '',
                                                    product_code: '',
                                                    quantity: '',
                                                    uom_code: null,
                                                    tax_code: null,
                                                    include_ppn: false,
                                                    is_production: false,
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
                    <table class="w-full rounded-lg" style="overflow: visible;">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Product Code
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/6">
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
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Incl. PPN
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Prod.
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
                                        <SelectProduct
                                            @select="(e) => onProductSelect(product, e)"
                                            :value="product.product_code"
                                            :ppnType="state.ppn_type"
                                            required="true"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `printing.products.${i}.product_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.product_code`
                                                ][0].replace(
                                                    `printing.products.${i}.product_code `,
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
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none rounded-lg"
                                            placeholder="Item name"
                                            v-model="product.name"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `printing.products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.name`
                                                ][0].replace(
                                                    `printing.products.${i}.name `,
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
                                                    `printing.products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.quantity`
                                                ][0].replace(
                                                    `printing.products.${i}.quantity `,
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
                                                {{ uom.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `printing.products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.uom_code`
                                                ][0].replace(
                                                    `printing.products.${i}.uom_code `,
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
                                                {{ tax.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `printing.products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.tax_code`
                                                ][0].replace(
                                                    `printing.products.${i}.tax_code `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.include_ppn"
                                                class="w-4 h-4 accent-red-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.include_ppn ? 'text-red-500 font-semibold' : 'text-gray-400'">
                                                {{ product.include_ppn ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.is_production"
                                                class="w-4 h-4 accent-blue-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.is_production ? 'text-blue-500 font-semibold' : 'text-gray-400'">
                                                {{ product.is_production ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
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
                                                    `printing.products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.price`
                                                ][0].replace(
                                                    `printing.products.${i}.price `,
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
                                            {{ product.quantity && product.price ? nom(Math.round(parseFloat(product.quantity) * calculatePriceWithTax(parseFloat(product.price), product.tax_code, product.include_ppn) * 100) / 100) : '0' }}
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
                                                    `printing.products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `printing.products.${i}.description`
                                                ][0].replace(
                                                    `printing.products.${i}.description `,
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
                                <td colspan="11">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.printing.products.push(
                                                    {
                                                        name: '',
                                                        product_code: '',
                                                        quantity: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        include_ppn: false,
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
                    <table class="w-full rounded-lg" style="overflow: visible;">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Product Code
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Supplier Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/6">
                                    Account Number
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px hidden">
                                    UOM
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px hidden">
                                    Tax
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-100px text-center">
                                    Prod.
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
                                        <SelectProduct
                                            @select="(e) => onProductSelect(product, e)"
                                            :value="product.product_code"
                                            :ppnType="state.ppn_type"
                                            required="true"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `payment.products.${i}.product_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.product_code`
                                                ][0].replace(
                                                    `payment.products.${i}.product_code `,
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
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none rounded-lg"
                                            placeholder="Supplier name"
                                            v-model="product.name"
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `payment.products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.name`
                                                ][0].replace(
                                                    `payment.products.${i}.name `,
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
                                                    `payment.products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.description`
                                                ][0].replace(
                                                    `payment.products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="hidden">
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
                                                {{ uom.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `payment.products.${i}.uom_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.uom_code`
                                                ][0].replace(
                                                    `payment.products.${i}.uom_code `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="hidden">
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
                                                {{ tax.name }}
                                            </option>
                                        </select>
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `payment.products.${i}.tax_code`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.tax_code`
                                                ][0].replace(
                                                    `payment.products.${i}.tax_code `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center h-full">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="product.is_production"
                                                class="w-4 h-4 accent-blue-500 cursor-pointer"
                                            />
                                            <span class="text-xs" :class="product.is_production ? 'text-blue-500 font-semibold' : 'text-gray-400'">
                                                {{ product.is_production ? 'Yes' : 'No' }}
                                            </span>
                                        </label>
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
                                                    `payment.products.${i}.price`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `payment.products.${i}.price`
                                                ][0].replace(
                                                    `payment.products.${i}.price `,
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
                                <td colspan="11">
                                    <button
                                        type="button"
                                        class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg"
                                        @click="
                                            () =>
                                                state.prd.payment.products.push(
                                                    {
                                                        name: '',
                                                        product_code: '',
                                                        uom_code: null,
                                                        tax_code: null,
                                                        is_production: false,
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
                </div>
            <div v-if="state.is_bundle_mode">
                <div v-for="(bundle, bi) in state.bundle_groups" :key="bi" class="border rounded-lg mb-4" style="overflow:hidden">
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b" style="gap:12px;flex-wrap:wrap">
                        <div class="flex items-center gap-2" style="flex-wrap:wrap">
                            <span class="text-xs" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:2px 8px;border-radius:9999px">Bundle {{ bi+1 }}</span>
                            <b class="text-sm">{{ bundle.name || 'Nama Paket' }}</b>
                            <span class="text-xs" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;padding:2px 8px;border-radius:9999px">{{ bundle.components.length }} item</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Paket: <b style="color:#111827">Rp {{ bundle.sets_qty && bundle.set_price ? nom(Math.round(parseFloat(bundle.sets_qty||0)*calculatePriceWithTax(parseFloat(bundle.set_price), bundle.tax_code, bundle.include_ppn)*100)/100) : '0' }}</b></span>
                            <button type="button" class="text-xl px-2 h-40px text-[#667085] hover:bg-gray-100 rounded" @click="setBundleDelete(bi)"><i class="ri-delete-bin-line"></i></button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-2"><span class="text-xs" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:2px 8px;border-radius:9999px">Paket / Bundle</span></div>
                        <div class="border rounded-lg" style="overflow:hidden">
                            <table class="w-full rounded-lg" style="overflow:visible">
                                <thead class="bg-gray-100">
                                    <tr class="border-b">
                                        <th class="text-left text-sm px-4 py-2" style="width:16%">Product Code</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:18%">Nama Paket</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">Qty (Set)</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">UOM</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">Tax</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:90px;text-align:center">Incl. PPN</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:120px">Price / Set</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:120px">Subtotal</th>
                                        <th class="text-left text-sm px-4 py-2">Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b">
                                        <td><div class="flex flex-col gap-1"><SelectProduct @select="(e)=>onBundleProductSelect(bundle,e)" :value="bundle.product_code" :ppnType="state.ppn_type" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.product_code`]">{{ state.errors[`bundle_groups.${bi}.product_code`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><input type="text" class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" placeholder="Nama Paket" v-model="bundle.name" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.name`]">{{ state.errors[`bundle_groups.${bi}.name`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><INumber class="w-full h-40px pl-4 pr-1 text-sm bg-transparent" placeholder="0" v-model="bundle.sets_qty" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.sets_qty`]">{{ state.errors[`bundle_groups.${bi}.sets_qty`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><select class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" v-model="bundle.uom_code"><option value="">Select UOM</option><option v-for="uom in uoms" :key="uom.id" :value="uom.code">{{ uom.name }}</option></select><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.uom_code`]">{{ state.errors[`bundle_groups.${bi}.uom_code`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><select class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" v-model="bundle.tax_code"><option value="">Select Tax</option><option v-for="tax in taxes" :key="tax.id" :value="tax.code">{{ tax.name }}</option></select><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.tax_code`]">{{ state.errors[`bundle_groups.${bi}.tax_code`][0] }}</span></div></td>
                                        <td class="text-center"><label class="flex flex-col items-center gap-1 cursor-pointer"><input type="checkbox" v-model="bundle.include_ppn" class="w-4 h-4 accent-red-500 cursor-pointer" /><span class="text-xs" :class="bundle.include_ppn ? 'text-red-500 font-semibold' : 'text-gray-400'">{{ bundle.include_ppn ? 'Yes' : 'No' }}</span></label></td>
                                        <td><div class="flex flex-col gap-1"><INumber class="w-full h-40px pl-4 pr-1 text-sm bg-transparent" placeholder="0" v-model="bundle.set_price" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.set_price`]">{{ state.errors[`bundle_groups.${bi}.set_price`][0] }}</span></div></td>
                                        <td><div class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center">{{ bundle.sets_qty && bundle.set_price ? nom(Math.round(parseFloat(bundle.sets_qty||0)*calculatePriceWithTax(parseFloat(bundle.set_price), bundle.tax_code, bundle.include_ppn)*100)/100) : '0' }}</div></td>
                                        <td><textarea class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent" placeholder="Deskripsi" v-model="bundle.description"></textarea></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="px-4 pb-4">
                        <div class="flex items-center gap-2 mb-2" style="flex-wrap:wrap">
                            <span class="text-xs" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;padding:2px 8px;border-radius:9999px">Isi per Paket</span>
                            <span class="text-xs text-gray-500" style="margin-left:auto">Subtotal Isi: <b style="color:#111827">Rp {{ nom(bundle.components.filter(c=>c.name&&c.name.trim()!=='').reduce((a,c)=>a+(c.quantity && c.price ? Math.round(parseFloat(c.quantity)*calculatePriceWithTax(parseFloat(c.price), c.tax_code, c.include_ppn)*100)/100 : 0),0)) }}</b></span>
                        </div>
                        <div class="border rounded-lg" style="overflow:hidden">
                            <table class="w-full rounded-lg" style="overflow:visible">
                                <thead class="bg-gray-100">
                                    <tr class="border-b">
                                        <th class="text-left text-sm px-4 py-2">Product Code</th>
                                        <th class="text-left text-sm px-4 py-2">Nama Komponen</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:90px">Qty / Set</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:90px">Total Qty</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">UOM</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">Tax</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:80px;text-align:center">Incl PPN</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:80px;text-align:center">Prod.</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">Price / Pcs</th>
                                        <th class="text-left text-sm px-4 py-2" style="width:110px">Subtotal</th>
                                        <th class="text-left text-sm px-4 py-2">Deskripsi</th>
                                        <th style="width:60px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b" v-for="(comp, ci) in bundle.components" :key="ci">
                                        <td><div class="flex flex-col gap-1"><SelectProduct @select="(e)=>onBundleComponentSelect(comp,e)" :value="comp.product_code" :ppnType="state.ppn_type" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.product_code`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.product_code`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><input type="text" class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" placeholder="Nama" v-model="comp.name" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.name`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.name`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><INumber class="w-full h-40px pl-4 pr-1 text-sm bg-transparent" placeholder="0" v-model="comp.qty_per_set" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.qty_per_set`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.qty_per_set`][0] }}</span></div></td>
                                        <td><div class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center" style="background:#f9fafb">{{ comp.quantity || '0' }}</div></td>
                                        <td><div class="flex flex-col gap-1"><select class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" v-model="comp.uom_code"><option value="">Select UOM</option><option v-for="uom in uoms" :key="uom.id" :value="uom.code">{{ uom.name }}</option></select><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.uom_code`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.uom_code`][0] }}</span></div></td>
                                        <td><div class="flex flex-col gap-1"><select class="w-full h-40px pl-4 pr-1 text-sm bg-transparent border-none" v-model="comp.tax_code"><option value="">Select Tax</option><option v-for="tax in taxes" :key="tax.id" :value="tax.code">{{ tax.name }}</option></select><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.tax_code`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.tax_code`][0] }}</span></div></td>
                                        <td class="text-center"><label class="flex flex-col items-center gap-1 cursor-pointer"><input type="checkbox" v-model="comp.include_ppn" class="w-4 h-4 accent-red-500 cursor-pointer" /><span class="text-xs" :class="comp.include_ppn ? 'text-red-500 font-semibold' : 'text-gray-400'">{{ comp.include_ppn ? 'Yes' : 'No' }}</span></label></td>
                                        <td class="text-center"><label class="flex flex-col items-center gap-1 cursor-pointer"><input type="checkbox" v-model="comp.is_production" class="w-4 h-4 accent-blue-500 cursor-pointer" /><span class="text-xs" :class="comp.is_production ? 'text-blue-500 font-semibold' : 'text-gray-400'">{{ comp.is_production ? 'Yes' : 'No' }}</span></label></td>
                                        <td><div class="flex flex-col gap-1"><INumber class="w-full h-40px pl-4 pr-1 text-sm bg-transparent" placeholder="0" v-model="comp.price" /><span class="text-xs text-red-500 pl-4" v-if="state.errors[`bundle_groups.${bi}.components.${ci}.price`]">{{ state.errors[`bundle_groups.${bi}.components.${ci}.price`][0] }}</span></div></td>
                                        <td><div class="w-full h-40px pl-4 pr-1 text-sm bg-transparent flex items-center">{{ comp.quantity && comp.price ? nom(Math.round(parseFloat(comp.quantity)*calculatePriceWithTax(parseFloat(comp.price), comp.tax_code, comp.include_ppn)*100)/100) : '0' }}</div></td>
                                        <td><textarea class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent" placeholder="Deskripsi" v-model="comp.description"></textarea></td>
                                        <td><button type="button" class="text-xl px-4 h-40px text-[#667085] hover:bg-gray-100 rounded" @click="setBundleDelete(bi, ci)"><i class="ri-delete-bin-line"></i></button></td>
                                    </tr>
                                </tbody>
                                <tfoot><tr><td colspan="12"><button type="button" class="text-sm px-4 py-2 bg-blue-gray-200 text-blue-gray-600 hover:bg-blue-gray-300 items-center justify-center flex gap-4 w-full rounded-b-lg" @click="addBundleComponent(bi)"><i class="ri-add-box-line text-xl"></i><span>Add Component</span></button></td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-2" style="flex-wrap:wrap">
                    <button type="button" class="text-sm px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 flex items-center gap-2" @click="addBundleGroup()"><i class="ri-add-line"></i> Add Bundle</button>
                </div>
                <p class="text-xs text-red-500 mt-2" v-if="state.errors.bundle_groups">{{ state.errors.bundle_groups[0] }}</p>
            </div>

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
                        v-if="state.is_po_deposit == false || state.is_bundle_mode"
                    >
                        <i class="ri-save-line text-xl"></i>
                        <strong>{{
                            currentUrl.includes("add") ? "Verify" : "Update"
                        }}</strong>
                    </button>
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
        title="Tax Required"
        :content="taxAlertMessage"
        :show="showTaxAlert"
        @hide="
            () => {
                showTaxAlert = false;
            }
        "
    ></Alert>
    <Alert
        type="failed"
        title="Failed to save"
        content="Please fix the validation errors below before proceeding."
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
