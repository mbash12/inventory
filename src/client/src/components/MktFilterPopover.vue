<script setup>
import { reactive, ref, watch } from "vue";

// fields: [{ key, label, options: [{ value, label }] }]; values: { [key]: value }
const props = defineProps({ fields: Array, values: Object, active: Boolean });
const emit = defineEmits(["apply", "reset"]);
const open = ref(false);
const draft = reactive({});
watch(open, (isOpen) => {
    if (isOpen) Object.assign(draft, props.values);
});
const apply = () => {
    open.value = false;
    emit("apply", { ...draft });
};
const reset = () => {
    open.value = false;
    emit("reset");
};
</script>

<template>
    <div class="relative">
        <button
            class="h-45px px-4 whitespace-nowrap border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
            @click="open = !open"
        >
            <i class="ri-filter-3-fill text-xl" :class="active ? 'text-red-500' : ''"></i>
            <strong>Filters</strong>
        </button>
        <div
            class="fixed top-0 left-0 w-full h-full bg-[#00000011] z-1"
            @click="open = false"
            :class="open ? 'block' : 'hidden'"
        ></div>
        <div
            class="absolute z-1 mt-2 transition-all transform origin-top-left"
            :class="open ? 'max-h-500px opacity-100 scale-100' : 'max-h-0 opacity-30 scale-10 overflow-hidden'"
        >
            <div class="w-280px bg-white rounded-lg shadow-2xl border">
                <div class="w-full p-4 flex flex-col gap-3 text-left">
                    <label v-for="field in fields" :key="field.key" class="flex flex-col gap-1">
                        <strong class="font-semibold text-blue-grayy-700 text-sm">{{ field.label }}</strong>
                        <select v-model="draft[field.key]" class="w-full border rounded p-1 text-sm">
                            <option v-for="option in field.options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>
                <div class="border-t px-4 py-2 flex justify-end items-center gap-2">
                    <button
                        class="h-32px px-4 border bg-white rounded-lg gap-2 shadow hover:shadow-sm hover:bg-gray-50 flex items-center text-sm"
                        @click="open = false"
                    >
                        <strong>Cancel</strong>
                    </button>
                    <button
                        class="h-32px px-4 border bg-white rounded-lg gap-2 shadow hover:shadow-sm hover:bg-gray-50 flex items-center text-sm"
                        @click="reset"
                    >
                        <strong>Reset</strong>
                    </button>
                    <button
                        class="h-32px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center text-sm"
                        @click="apply"
                    >
                        <strong>Apply</strong>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
