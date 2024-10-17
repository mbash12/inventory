<script setup>
import { reactive, watch } from "vue";

const props = defineProps({
    show: Boolean,
    warehouse: Array,
    warehouses: Array,
    limit: Number
});

const handleSetWarehouse = (e) => {
    if (e.target.checked) {
        filters.warehouse = [...filters.warehouse, e.target.value];
    } else {
        filters.warehouse = filters.warehouse.filter(
            (d) => d != e.target.value
        );
    }
};
const filters = reactive({
    warehouse: [],
    limit: 10
});

watch(props, () => {
    if (props.show) {
        filters.warehouse = props.warehouse;
        filters.limit = props.limit;
    }
});
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
                    <span
                        class="text-left text-sm my-3 pl-4 font-medium text-gray-500"
                        >Select warehouses</span
                    >
                    <label
                        v-for="(option, i) in warehouses"
                        class="px-4 py-1 text-left flex items-center gap-4"
                        :key="i"
                    >
                        <input
                            type="checkbox"
                            :value="option.id"
                            @input="handleSetWarehouse"
                            :checked="filters.warehouse.includes(option.id+'')"
                        />
                        <span class="text-sm"> {{ option.name }} </span>
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
                    <div class="px-4">
                        <button
                            class="flex px-3 h-12 gap-2 items-center justify-center bg-app-500 text-white rounded-full w-full"
                            @click="$emit('apply', filters)"
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
