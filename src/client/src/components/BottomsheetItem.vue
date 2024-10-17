<script setup>
import { onMounted, reactive, watch } from "vue";
const props = defineProps({
    show: Boolean,
    actual_quantity: Number,
    delivered_at: Date,
});
const state = reactive({
    actual_quantity: 0,
    delivered_at: null,
});
watch(props, async () => {
    if (props.show) {
        state.actual_quantity = props.actual_quantity;
        state.delivered_at = props.delivered_at;
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
                    <span>Actions</span>
                    <button class="p-4" @click="$emit('hide')">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="flex flex-col overflow-auto flex-1 pb-4 gap-2">
                    <div class="px-4">
                        <span class="text-xs text-gray-500 py-2 block"
                            >Set Actual Quantity</span
                        >
                        <div
                            class="flex bg-gray-100 rounded-md overflow-hidden"
                        >
                            <input
                                type="number"
                                class="flex-1 h-44px bg-transparent px-4 text-sm rounded-l-md"
                                placeholder="Actual Quantity"
                                v-model="state.actual_quantity"
                            />
                            <button
                                class="px-4 py-2 text-left flex items-center justify-center gap-4 bg-blue-100 text-app-900"
                                @click="$emit('update',state.actual_quantity)"
                            >
                                <span class="text-sm font-medium">
                                    Set Actual
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="px-4">
                        <button
                            class="px-4 py-2 text-left flex items-center justify-center gap-4 bg-app-100 w-full text-white rounded-full mt-4"
                            @click="$emit('deliver')"
                        >
                            <i class="ri-checkbox-circle-line text-xl"></i>
                            <span class=""> Mark as Delivered </span>
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
