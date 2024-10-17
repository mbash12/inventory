<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import dayjs from 'dayjs';
const props = defineProps({
    show: Boolean,
    value: String,
});
const val = ref("");

// watch(
//     () => props.show,
//     async () => {
//         if (props.show) {
//             val.value = dayjs(props.value).format("YYYY-MM-DD");
//             console.log(props.value);
//             console.log(val.value);
//         }
//     }
// );
</script>
<template>
    <Transition name="sheet">
        <div
            class="flex fixed top-0 left-0 w-full h-full z-10 items-end transition"
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
                    <span>Add new vendor</span>
                    <button class="p-4" @click="$emit('hide')">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="flex flex-col overflow-auto flex-1 pb-2">
                    <div class="flex flex-col gap-4 w-full p-4 pt-0">
                        <input
                            type="text"
                            name=""
                            id=""
                            class="border-b   w-full h-10 bg-transparent"
                            v-model="val"
                            placeholder="Vendor name"
                        />
                        <button
                            class="flex p-2 gap-2 items-center justify-center bg-app-500 text-white rounded-full"
                            @click="$emit('changed', val)"
                        >
                            <i class="ri-save-line text-xl"></i>
                            <span class="text-sm mt-[2px]"> Save </span>
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
