<script setup>
import { reactive, watch } from "vue";
import {
    deleteProject,
    getProjectList,
    nom,
    goto,
    sortByProperty,
} from "../services/service";
const emit = defineEmits(["action", "delete"]);
const props = defineProps({
    show: Boolean,
    items: Array,
    selected: Number,
});
const state = reactive({
    title: null,
});
watch(props, () => {
    if (props.show) {
        if (props.selected !== null) {
            let data = { ...props.items[props.selected] };
            state.title = data.title;
        } else {
            state.title = null;
        }
    }
});
const emitAction = () => {
    emit("action", {
        title: state.title,
    });
};
const emitDelete = () => {
    emit("delete");
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
                <strong>{{
                    selected !== null ? "Edit Item" : "Add Item"
                }}</strong>
                <div class="flex w-full">
                    <label class="flex flex-col items-start relative w-full">
                        <span class="text-sm text-blue-gray-400">Title</span>
                        <input
                            type="text"
                            class="border-b w-full h-10 bg-transparent text-sm text-black"
                            v-model="state.title"
                        />
                    </label>
                </div>
                <div class="flex mt-2 gap-2">
                    <button
                        type="button"
                        v-show="selected !== null"
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 w-1/2 text-app-500 bg-gray-100 rounded"
                        @click="emitDelete"
                    >
                        <span class=""> Delete </span>
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 flex-1 bg-gray-100 rounded"
                        @click="$emit('hide')"
                    >
                        <span class=""> Cancel </span>
                    </button>

                    <button
                        v-show="selected === null"
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 w-1/2 bg-app-500 text-white rounded"
                    >
                        <span class=""> Save </span>
                    </button>
                </div>
                <div class="flex gap-2" v-show="selected !== null">
                    <button
                        class="px-4 py-2 text-left flex items-center justify-center gap-4 w-full bg-app-500 text-white rounded"
                    >
                        <span class=""> Update </span>
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
