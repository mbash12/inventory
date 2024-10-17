<script setup>
import { reactive, watch } from "vue";

const emit = defineEmits(["action", "delete"]);
const props = defineProps({
    show: Boolean,
});
const state = reactive({
    invoice_number: null,
    invoice_date: null,
    pic: null,
    status: null,
    note: null,
});

watch(props, () => {
    if (props.show) {
        state.invoice_number = null;
        state.invoice_date = null;
        state.pic = null;
        state.status = null;
        state.note = null;
    }
});
const emitAction = () => {
    emit("action", {
        invoice_number: state.invoice_number,
        invoice_date: state.invoice_date,
        pic: state.pic,
        status: state.status,
        note: state.note,
    });
};
</script>
<template>
    <Transition name="sheet">
        <div
            class="w-full h-full fixed top-0 left-0 bg-[#00000022] flex items-center justify-center p-4 transition"
            v-if="show"
        >
            <form
                @submit.prevent="emitAction"
                class="w-full p-4 rounded-lg bg-white shadow-lg flex flex-col gap-4 transition modalbody"
            >
                <strong>Add Progress</strong>
                <label class="flex flex-col items-start">
                    <span class="text-xs text-blue-gray-400">Invoice Number</span>
                    <input
                        type="text"
                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                        v-model="state.invoice_number"
                    />
                </label>
                <label class="flex flex-col items-start">
                    <span class="text-xs text-blue-gray-400">Invoice Date</span>
                    <input
                        type="date"
                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                        v-model="state.invoice_date"
                    />
                </label>

                <!-- <div class="flex w-full">
                    <label class="flex flex-col items-start relative w-full">
                        <span class="text-xs text-blue-gray-400">Quantity</span>
                        <input
                            type="number"
                            class="border-b w-full h-10 bg-transparent text-sm text-black pr-20"
                            v-model="state.quantity"
                            required
                        />
                    </label>
                </div> -->
                <div class="flex w-full gap-4">
                    <label class="flex flex-col items-start w-full">
                        <span class="text-xs text-blue-gray-400">PIC *</span>
                        <select
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.pic"
                            required
                        >
                        <option value="marketing">Marketing</option>
                        <option value="finance">Finance</option>
                        <option value="delivery">Delivery</option>
                        </select>
                    </label>
                    <label class="flex flex-col items-start w-full">
                        <span class="text-xs text-blue-gray-400">Status *</span>
                        <select
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.status"
                            required
                        >
                        <option value="progress">Progress</option>
                        <option value="sent">Sent</option>
                        </select>
                    </label>
                </div>
                <div class="flex w-full">
                    <label class="flex flex-col items-start relative w-full">
                        <span class="text-xs text-blue-gray-400"
                            >Note *</span
                        >
                        <textarea
                            class="border-b w-full h-20 bg-transparent text-sm text-black pr-20 pt-2"
                            v-model="state.note"
                            required
                        >
                        </textarea>
                    </label>
                </div>
                <div class="flex mt-2 gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 w-1/2 bg-gray-100 rounded"
                        @click="$emit('hide')"
                    >
                        <span class=""> Cancel </span>
                    </button>

                    <button
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 w-1/2 bg-app-500 text-white rounded"
                    >
                        <span class=""> Save </span>
                    </button>
                </div>
            </form>
        </div>
    </Transition>
</template>

<style>
/* .drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100vw);
} */

.sheet-enter-from .modalbody,
.sheet-leave-to .modalbody {
    opacity: 0;
    transform: translateY(100%);
}
/* .sheet-enter-from .sheetct,
.sheet-leave-to .sheetct,
.sheet-enter-from .sheetcl,
.sheet-leave-to .sheetcl {
    transform: translateY(100%);
} */
</style>
