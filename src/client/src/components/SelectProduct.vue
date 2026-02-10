<script setup>
import { onMounted, onUnmounted, reactive, watch, computed, ref } from "vue";
import { getProductList } from "../services/service";
const emit = defineEmits(["select"]);
const props = defineProps({
    value: String | Number,
    code: String | Number,
    required: String,
    inline: Boolean,
    disabled: Boolean,
    ppnType: {
        type: String,
        default: 'ppn'
    }
});

// Ensure display value is always a string
const displayValue = computed(() => {
    if (props.value === null || props.value === undefined) {
        return '';
    }
    if (typeof props.value === 'object') {
        return props.value.name || props.value.label || String(props.value) || '';
    }
    return String(props.value);
});

const state = reactive({
    focus: false,
    search: null,
    list: [],
    allProducts: [], // Store all products for client-side filtering
    dropdownPosition: { top: 0, left: 0, width: 0 },
});

const dropdownStyle = computed(() => {
    return {
        top: `${state.dropdownPosition.top}px`,
        left: `${state.dropdownPosition.left}px`,
        width: `${state.dropdownPosition.width}px`,
        minWidth: '250px',
    };
});

// Ref for the input element
const inputRef = ref(null);

// Calculate dropdown position when focusing
const updateDropdownPosition = () => {
    if (inputRef.value) {
        const rect = inputRef.value.getBoundingClientRect();
        state.dropdownPosition = {
            top: rect.bottom + window.scrollY + 2,
            left: rect.left + window.scrollX,
            width: Math.max(rect.width, 250),
        };
    }
};

onMounted(() => {
    getList();
});

const getList = () => {
    getProductList(props.ppnType).then((r) => {
        if (r.code == 200) {
            state.allProducts = r.data;
            filterProducts();
        }
    });
};

const filterProducts = () => {
    let filtered = state.allProducts;
    
    // If search term exists, filter products
    if (state.search && state.search.trim() !== '') {
        const searchLower = state.search.toLowerCase();
        filtered = state.allProducts.filter(p => 
            p.name.toLowerCase().includes(searchLower) || 
            p.code.toLowerCase().includes(searchLower)
        );
    }
    
    // Map to the format needed for display
    state.list = filtered.map((e) => ({ 
        value: e.code, 
        label: e.name,
        code: e.code,
        name: e.name,
        selling_price: e.selling_price,
        cost_price: e.cost_price,
        description: e.description,
        unit: e.unit,
        tax: e.tax,
        product_group: e.product_group,
    }));
};

const onSelect = (item) => {
    // Emit full product data for parent component
    emit("select", { 
        name: item.name,
        code: item.code,
        selling_price: item.selling_price,
        cost_price: item.cost_price,
        description: item.description,
        unit: item.unit,
        tax: item.tax,
        product_group: item.product_group,
    });
};

const onFocus = () => {
    state.focus = true;
    updateDropdownPosition();
};

let waiter;
watch(
    () => state.search,
    () => {
        clearTimeout(waiter);
        waiter = setTimeout(filterProducts, 300);
    }
);

// Watch for ppnType changes and reload product list
watch(
    () => props.ppnType,
    () => {
        getList();
    }
);

// Update position on scroll/resize
onMounted(() => {
    window.addEventListener('scroll', updateDropdownPosition);
    window.addEventListener('resize', updateDropdownPosition);
});

onUnmounted(() => {
    window.removeEventListener('scroll', updateDropdownPosition);
    window.removeEventListener('resize', updateDropdownPosition);
});
</script>
<template>
    <div ref="inputRef" class="relative w-full h-40px rounded-lg border border-gray-200">
        <div class="w-full h-full relative flex items-center bg-white rounded-lg" @click="$event.target.querySelector('input')?.focus()">
            <div class="w-full items-center text-sm truncate px-4 text-gray-700 pointer-events-none" v-if="!state.focus">
                {{ displayValue || 'Click to select...' }}
            </div>
            <input
                :title="displayValue"
                type="text"
                class="w-full h-full bg-white absolute top-0 left-0 text-sm truncate px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="state.focus ? 'opacity-100' : 'opacity-0 cursor-pointer'"
                @focus="onFocus"
                @blur="state.focus = false; state.search = null"
                placeholder="Type to search..."
                v-model="state.search"
                :disabled="props.disabled"
            />
            <div class="absolute right-2 text-gray-400 pointer-events-none" v-if="!state.focus">
                <i class="ri-arrow-down-s-line"></i>
            </div>
        </div>
        <div
            class="fixed max-h-300px bg-white z-[9999] shadow-lg rounded border overflow-auto flex flex-col"
            v-if="state.focus"
            :style="dropdownStyle"
        >
            <div
                class="w-full py-2 px-4 text-sm hover:bg-gray-50 bg-white cursor-pointer"
                v-if="state.list.length > 0"
                v-for="(item, i) in state.list"
                :key="item.code"
                @mousedown="onSelect(item)"
            >
                <div class="font-medium">{{ item.label }}</div>
                <div class="text-xs text-gray-500">{{ item.code }} - Rp {{ item.selling_price?.toLocaleString() || 0 }}</div>
            </div>
            <div class="w-full py-2 px-4 text-sm text-center text-gray-500" v-else>
                No products found
            </div>
        </div>
        <input type="text" required v-if="required == 'true' && value == null" name="hidden" class="opacity-0 h-1px w-full"/>
    </div>
</template>
