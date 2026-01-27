<script setup>
import { reactive, watch } from "vue";
import dayjs from "dayjs";

const emit = defineEmits(["action", "delete"]);
const props = defineProps({
    is_plan: Boolean,
    show: Boolean,
    selected: Object,
});
const state = reactive({
    is_plan: false,
    id: null,
    client_po_number: null,
    client_po_date: null,
    schedule_date: null,
    status: null,
    note: null,
});

watch(props, () => {
    if (props.show) {
        if (props.selected !== null) {
            state.id = props.selected.id;
            state.client_po_number = props.selected.client_po_number;
            state.client_po_date = dayjs(props.client_po_date).format(
                "YYYY-MM-DD"
            );
            state.schedule_date = dayjs(props.schedule_date).format(
                "YYYY-MM-DD"
            );
            state.status = props.selected.status;
            state.note = props.selected.note;
        } else {
            state.id = null;
            state.client_po_number = null;
            state.client_po_date = null;
            state.schedule_date = null;
            state.status = null;
            state.note = null;
        }
    }
});
const emitAction = () => {
    emit("action", {
        id: state.id,
        client_po_number: state.client_po_number,
        client_po_date: state.client_po_date,
        schedule_date: state.schedule_date,
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
                <strong>PO Action</strong>
                <div class="flex w-full gap-2" v-if="state.id == null">
                    <div
                        class="flex-1 p-2 border rounded flex items-center justify-center text-sm"
                        @click="state.is_plan = true"
                        :class="
                            state.is_plan
                                ? 'border-red-500'
                                : 'border-transparent'
                        "
                    >
                        Plan
                    </div>
                    <div
                        class="flex-1 p-2 border rounded flex items-center justify-center text-sm"
                        @click="state.is_plan = false"
                        :class="
                            !state.is_plan
                                ? 'border-red-500'
                                : 'border-transparent'
                        "
                    >
                        Follow Up
                    </div>
                </div>
                <label
                    class="flex flex-col items-start"
                    v-if="props.selected !== null || !state.is_plan"
                >
                    <span class="text-xs text-blue-gray-400">PO Number</span>
                    <input
                        type="text"
                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                        v-model="state.client_po_number"
                    />
                </label>
                <label
                    class="flex flex-col items-start"
                    v-if="props.selected !== null || !state.is_plan"
                >
                    <span class="text-xs text-blue-gray-400">PO Date</span>
                    <input
                        type="date"
                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                        v-model="state.client_po_date"
                        required
                    />
                </label>

                <label class="flex flex-col items-start w-full">
                    <span class="text-xs text-blue-gray-400">Plan Date *</span>
                    <input
                        type="date"
                        class="w-full h-10 border-b bg-transparent text-sm text-black"
                        v-model="state.schedule_date"
                        :disabled="state.id !== null"
                        :required="state.id == null"
                    />
                </label>
                <div class="flex w-full">
                    <label class="flex flex-col items-start relative w-full">
                        <span class="text-xs text-blue-gray-400">Note *</span>
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
                        <span class="">
                            {{ state.id || !state.is_plan ? "Save" : "Plan" }}
                        </span>
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
