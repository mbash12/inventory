<script setup>
const props = defineProps({
  show: Boolean,
  options: Array,
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
          <span>Select Warehouse</span>
          <button class="p-4" @click="$emit('hide')">
            <i class="ri-close-line text-xl"></i>
          </button>
        </div>
        <div class="flex flex-col overflow-auto flex-1 pb-2">
          <button
            v-for="(option, i) in options"
            :key="i"
            class="px-4 py-2 text-left flex items-center gap-4"
            @click="$emit('select', option)"
          >
            <span class="text-sm"> {{ option }} </span>
          </button>
        </div>
        <hr class="mb-4" />
        <label class="flex flex-col items-start gap-1 px-4">
          <span class="text-xs text-blue-gray-400 font-regular uppercase"
            >Add Warehouse</span
          >
          <div class="flex w-full mb-4 rounded-md overflow-hidden">
            <input
              type="number"
              name=""
              id=""
              class="w-full h-10 bg-gray-100 text-sm flex-1 px-4"
            />
            <button
              class="h-10 text-xs items-center justify-center bg-app-500 text-white px-4"
            >
              Add
            </button>
          </div>
        </label>
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
