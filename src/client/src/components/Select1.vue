<script setup>
import { onMounted, reactive, watch, computed } from "vue";
import { getCustomerList } from "../services/service";
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
        // If somehow an object is passed, try to get the name property or return empty string
        return props.value.name || props.value.label || String(props.value) || '';
    }
    return String(props.value);
});
const state = reactive({
    focus: false,
    search: null,
    list: [],
});
onMounted(() => {
    getList();
});

// Watch for changes in ppnType and refresh the list
watch(() => props.ppnType, () => {
    // Only refresh if we're not currently focused (to avoid disrupting user input)
    if (!state.focus) {
        getList();
    }
});

const getList = () => {
    getCustomerList(state.search, props.ppnType).then((r) => {
        if (r.code == 200) {
            state.list = r.data.map((e) => ({
                value: e.contact_code,
                label: e.name,
                contact_code: e.contact_code,
                contact_person: e.contact_person,
                email: e.email,
                phone: e.phone
            }));
        }
    });
};
const onSelect = (item) => {
    // Emit both label (name) and code for parent component
    emit("select", { 
        name: item.label, 
        code: item.contact_code,
        contact_person: item.contact_person 
    });
};
let waiter
watch(
    () => state.search,
    () => {
        clearTimeout(waiter)
        waiter = setTimeout(getList, 500);    
    }
);
</script>
<template>
    <div class="relative w-full h-full rounded-lg">
        <div class="w-full h-full relative flex items-center input">
            <div class="w-full  items-center text-sm truncate" :class="[props.inline ? 'pl-4' : 'pl-45px']">
                {{ displayValue }}
            </div>
            <input
                :title="displayValue"
                type="text"
                class="w-full h-full bg-white absolute top-0 left-0 text-sm truncate"
                @focus="state.focus = true"
                @blur="state.focus = false, state.search = null"
                :placeholder="displayValue"
                v-model="state.search"
                :class="[state.focus ? 'opacity-100' : 'opacity-0', props.inline ? 'pl-4' : 'pl-45px']"
                :disabled="props.disabled"
            />
        </div>
        <div
            class="absolute w-full max-h-300px bg-white z-2 top-50px shadow-lg rounded border overflow-auto flex flex-col"
            v-if="state.focus"
        >
            <div
                class="w-full py-2 px-4 text-sm hover:bg-gray-50 bg-white"
                v-if="state.list.length > 0"
                v-for="(item, i) in state.list"
                @mousedown="onSelect(item)"
            >
                <div class="font-medium">{{ item.label }}</div>
                <div class="text-xs text-gray-500">{{ item.contact_code }}</div>
            </div>
            <div class="w-full py-2 px-4 text-sm text-center text-gray-500 " v-else>
                 Not Found
            </div>
        </div>
        <input type="text" required v-if="required == 'true' && value == null" name="hidden" class="opacity-0 h-1px w-full"/>
    </div>
</template>
