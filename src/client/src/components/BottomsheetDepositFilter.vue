<script setup>
import { reactive, watch } from "vue";
const emit = defineEmits(["clear", "apply"]);
const props = defineProps({
    show: Boolean,
    status: Array,
    start_date: String,
    end_date: String,
    limit: Number
});
const filters = reactive({
    status: [],
    start_date: null,
    end_date: null,
    limit: 10,
});
const handleSetMulti = (e, type) => {
    if (e.target.checked) {
        filters[type] = [...filters[type], e.target.value];
    } else {
        filters[type] = filters[type].filter((d) => d != e.target.value);
    }
};
const handleSetSingle = (e, type) => {
    if (e.target.checked) {
        filters[type] = [e.target.value];
    } else {
        filters[type] = [null];
    }
};
const applyFilter = () => {
    emit("apply", filters);
};
const clearFilter = () => {
    emit("clear");
};
watch(props, async () => {
    if (props.show) {
        filters.status = props.status;
        filters.start_date = props.start_date;
        filters.end_date = props.end_date;
        filters.limit = props.limit;
    }
});
const status = [
    { title: "Open", value: "open" },
    { title: "Close", value: "close" },
];
</script>
<template>
    <Transition name="sheet">
        <div
            class="flex fixed top-0 left-0 w-full h-full z-10 items-end transition text-black"
            v-if="show"
        >
            <div
                class="absolute top-0 left-0 bg-[#00000022] backdrop-filter backdrop-blur-sm w-full h-full sheetbg transition cursor-pointer"
                @click="$emit('hide')"
            ></div>
            <div
                class="w-full rounded-t-2xl bg-white relative flex flex-col sheetct transition max-h-9/10 pt-2 shadow-lg"
            >
                <div
                    class="flex h-12 items-center justify-between pl-4 uppercase font-bold text-blue-gray-400 text-sm"
                >
                    <span>Filter</span>
                    <button class="p-4" @click="$emit('hide')">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="flex flex-col overflow-auto flex-1 pb-2">
                    <hr class="mb-3" />
                    <div class="flex">
                        <div class="w-full">
                            <span
                                class="text-left text-sm my-3 pl-4 font-medium text-gray-500 block"
                                >Status</span
                            >
                            <label
                                v-for="(option, i) in status"
                                class="px-4 py-1 text-left flex items-center gap-4"
                                :key="i"
                            >
                                <input
                                    type="checkbox"
                                    :value="option.value"
                                    @input="(e) => handleSetSingle(e, 'status')"
                                    :checked="
                                        filters.status.includes(option.value)
                                    "
                                />
                                <span class="text-sm">
                                    {{ option.title }}
                                </span>
                            </label>
                            
                        </div>
                        
                    </div>

                    <hr class="my-3" />
                    <span
                        class="text-left text-sm my-3 pl-4 font-medium text-gray-500"
                        >PO Date
                    </span>
                    <label class="flex items-center px-4 gap-4 mb-2">
                        <span class="text-sm w-20 text-left"> Start Date </span>
                        <input
                            type="date"
                            class="flex-1 border rounded text-sm h-8 px-2"
                            v-model="filters.start_date"
                        />
                    </label>
                    <label class="flex items-center px-4 gap-4 mb-2">
                        <span class="text-sm w-20 text-left"> End Date </span>
                        <input
                            type="date"
                            class="flex-1 border rounded text-sm h-8 px-2"
                            v-model="filters.end_date"
                        />
                    </label>
                    <hr class="my-3" />
                    <label class="flex items-center px-4 gap-4 mb-2">
                        <span class="text-sm w-20 text-left"> Result Limit </span>
                        <select
                            class="flex-1 border rounded text-sm h-8 px-2"
                            v-model="filters.limit"
                        >
                            <option value="1">1</option>
                            <option value="10">10</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>
                    <hr class="my-3" />
                    <div class="px-4 flex gap-4">
                        <button
                            class="flex px-3 h-12 gap-2 items-center justify-center bg-gray-100 text-black rounded-full w-full"
                            @click="clearFilter"
                        >
                            <span class="text-sm mt-1"> Clear Filter </span>
                        </button>
                        <button
                            class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                            @click="applyFilter"
                        >
                            <span class="text-sm mt-1"> Apply Filter </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style>
/* .drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100vw);
} */

.sheet-enter-from .sheetbg,
.sheet-leave-to .sheetbg {
    opacity: 0;
}
.sheet-enter-from .sheetct,
.sheet-leave-to .sheetct,
.sheet-enter-from .sheetcl,
.sheet-leave-to .sheetcl {
    transform: translateY(100%);
}
</style>
