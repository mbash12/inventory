<script setup>
import { reactive, watch } from "vue";
import {nom} from "../services/service";
const emit = defineEmits(["action", "delete"]);
const props = defineProps({
    show: Boolean,
    user: Object,
    project: Object
});
const state = reactive({
    invoice_date: null,
    invoice_number: null,
    client_po_number: null,
    client_po_date: null,
    delivery_deadline: null,
    production_deadline: null,
    po_deadline: null,
    invoice_status: null,
    invoice_pic: null,
    invoiced_amount: null,
    note: null,

    is_plan: true,
    inv_tab: 0,
});

watch(props, () => {
    if (props.show) {
        state.invoice_date = null;
        state.invoice_number = null;
        state.client_po_number = null;
        state.client_po_date = null;
        state.delivery_deadline = null;
        state.production_deadline = null;
        state.po_deadline = null;
        state.invoice_status = null;
        state.invoice_pic = null;
        state.invoiced_amount = props.project?.remaining_amount ?? props.project?.total_price
        state.note = null;
        state.alert_time = null;
        state.is_plan = true;
        state.inv_tab = 0;
    }
});
const emitAction = () => {
    emit("action", {
        invoice_date: state.invoice_date,
        invoice_number: state.invoice_number,
        client_po_number: state.client_po_number,
        client_po_date: state.client_po_date,
        delivery_deadline: state.delivery_deadline,
        production_deadline: state.production_deadline,
        po_deadline: state.po_deadline,
        invoice_status: state.invoice_status,
        invoice_pic: state.invoice_pic,
        invoiced_amount: state.invoiced_amount,
        note: state.note,
        alert_time: state.alert_time,
        is_plan: state.is_plan,
        inv_tab: state.inv_tab,
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
                <strong>Add Update</strong>
                <div
                    class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                    v-if="user.position == 'marketing'"
                >
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            state.is_plan ? 'text-red-500' : 'text-gray-400'
                        "
                        @click="() => (state.is_plan = true)"
                    >
                        Set Deadline
                    </div>
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            !state.is_plan ? 'text-red-500' : 'text-gray-400'
                        "
                        @click="() => (state.is_plan = false)"
                    >
                        Set PO Number
                    </div>
                </div>
                <div
                    class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                    v-if="user.position == 'delivery'"
                >
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            state.is_plan ? 'text-red-500' : 'text-gray-400'
                        "
                        @click="() => (state.is_plan = true)"
                    >
                        Set Production Deadline
                    </div>
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            !state.is_plan ? 'text-red-500' : 'text-gray-400'
                        "
                        @click="() => (state.is_plan = false)"
                    >
                        Set Delivery Deadline
                    </div>
                </div>
                <div
                    class="flex h-10 w-full bg-gray-50 rounded-md mb-2"
                    v-if="user.position == 'finance'"
                >
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            state.inv_tab == 0
                                ? 'text-red-500'
                                : 'text-gray-400'
                        "
                        @click="() => (state.inv_tab = 0)"
                    >
                        Update Progress
                    </div>
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            state.inv_tab == 1
                                ? 'text-red-500'
                                : 'text-gray-400'
                        "
                        @click="() => (state.inv_tab = 1)"
                    >
                        Set Invoice
                    </div>
                    <div
                        class="flex-1 h-full flex items-center justify-center font-medium text-sm cursor-pointer"
                        :class="
                            state.inv_tab == 2
                                ? 'text-red-500'
                                : 'text-gray-400'
                        "
                        @click="() => (state.inv_tab = 2)"
                    >
                        Delete Invoice
                    </div>
                </div>
                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'delivery'"
                >
                    <label
                        class="flex flex-col flex-1 items-start"
                        v-if="state.is_plan"
                    >
                        <span class="text-xs text-blue-gray-400"
                            >Production Deadline*</span
                        >
                        <input
                            type="date"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.production_deadline"
                        />
                    </label>
                    <label
                        class="flex flex-col flex-1 items-start"
                        v-if="!state.is_plan"
                    >
                        <span class="text-xs text-blue-gray-400"
                            >Delivery Deadline*</span
                        >
                        <input
                            type="date"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.delivery_deadline"
                        />
                    </label>
                </div>

                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'marketing' && !state.is_plan"
                >
                    <label class="flex flex-col flex-1 items-start">
                        <span class="text-xs text-blue-gray-400"
                            >PO Number*</span
                        >
                        <input
                            type="text"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.client_po_number"
                            required
                        />
                    </label>
                    <label class="flex flex-col flex-1 items-start">
                        <span class="text-xs text-blue-gray-400">PO Date*</span>
                        <input
                            type="date"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.client_po_date"
                            required
                        />
                    </label>
                </div>
                <div class="flex w-full gap-4">
                    <label
                        class="flex flex-col flex-1 items-start"
                        v-if="user.position == 'marketing' && state.is_plan"
                    >
                        <span class="text-xs text-blue-gray-400"
                            >PO Deadline*</span
                        >
                        <input
                            type="date"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.po_deadline"
                            required
                        />
                    </label>
                </div>
                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'finance' && state.inv_tab == 2"
                >
                    <label class="flex flex-col items-start w-full">
                        <span class="text-xs text-blue-gray-400">Invoice Number*</span>
                        <select
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.invoice_number"
                            required
                        >
                            <option v-for="inv in JSON.parse(props?.project?.invoices ?? '[]')" :key="inv.invoice_number" :value="inv.invoice_number">{{ inv.invoice_number }}</option>
                        </select>
                    </label>
                </div>
                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'finance' && state.inv_tab == 1"
                >
                    <label class="flex flex-col flex-1 items-start">
                        <span class="text-xs text-blue-gray-400"
                            >Invoice Number*</span
                        >
                        <input
                            type="text"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.invoice_number"
                        />
                    </label>
                    
                    <label
                        class="flex flex-col flex-1 items-start"
                    >
                        <span class="text-xs text-blue-gray-400"
                            >Invoice Date*</span
                        >
                        <input
                            type="date"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.invoice_date"
                        />
                    </label>
                </div>
                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'finance' && state.inv_tab == 1"
                >
                    <label class="flex flex-col flex-1 items-start">
                        <span class="text-xs text-blue-gray-400"
                            >Amount* <small>(Max: {{ nom(props.project?.remaining_amount ?? props.project?.total_price) }})</small></span
                        >
                        <input
                            type="number"
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.invoiced_amount"
                            :max="props.project?.remaining_amount ?? props.project?.total_price"
                        />
                    </label>
                </div>
                <div
                    class="flex w-full gap-4"
                    v-if="user.position == 'finance'  && state.inv_tab != 2"
                >
                    <label class="flex flex-col items-start w-full" v-if="state.inv_tab == 0">
                        <span class="text-xs text-blue-gray-400">PIC*</span>
                        <select
                            class="w-full h-10 border-b bg-transparent text-sm text-black"
                            v-model="state.invoice_pic"
                            required
                        >
                            <option value="marketing">Marketing</option>
                            <option value="finance">Finance</option>
                            <option value="delivery">Delivery</option>
                        </select>
                    </label>
                    <label class="flex flex-col items-start w-full">
                        <span class="text-xs text-blue-gray-400">Status*</span>
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
                <label
                    class="flex flex-col items-start w-full"
                    v-if="
                        user.position !== 'finance' &&
                        !(!state.is_plan && user.position === 'marketing')
                    "
                >

                </label>
                <div class="flex w-full">
                    <label class="flex flex-col items-start relative w-full">
                        <span class="text-xs text-blue-gray-400">Note*</span>
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
}*/

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
}*/
</style>
