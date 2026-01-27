<script setup>
import { reactive, watch } from "vue";
const props = defineProps({
  show: Boolean,
  selected: Number,
  items: Array,
});
const state = reactive({
  item: null,
});
watch(props, () => {
  if (props.show) {
    state.item = props.items.find((e) => e.id === props.selected);
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
        <div class="flex flex-col overflow-auto flex-1 pb-2">
          <div class="flex flex-col overflow-auto flex-1 pb-2">
            <div class="flex mt-2 gap-2 px-4">
              <router-link :to="'/delivery/details/' + selected" class="w-full">
                <button
                  class="px-4 py-2 text-left flex items-center justify-center gap-2 text-blue-500 w-full bg-blue-100 rounded"
                >
                  <i class="ri-article-line text-xl"></i>
                  <span class=""> Detail </span>
                </button>
              </router-link>
            </div>
          </div>
          <div
            class="flex flex-col overflow-auto flex-1 pb-6"
            v-if="state.item?.status !== 'delivered'"
          >
            <div class="flex mt-2 gap-2 px-4">
              <button
                class="px-4 py-2 text-left flex items-center justify-center gap-2 text-red-500 w-1/2 bg-gray-100 rounded"
                @click="$emit('delete')"
              >
                <i class="ri-delete-bin-line text-xl"></i>
                <span class=""> Delete </span>
              </button>
              <router-link :to="'/delivery/edit/' + selected" class="w-1/2">
                <button
                  class="px-4 py-2 text-left flex items-center justify-center gap-2 w-full bg-gray-100 rounded w-full"
                >
                  <i class="ri-edit-line text-xl"></i>
                  <span class=""> Edit </span>
                </button>
              </router-link>
            </div>
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
