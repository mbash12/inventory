<script setup>
import { reactive } from "vue";
const state = reactive({ image_open: false });
// let image = "/client/src/assets/product.jpg";
const props = defineProps({
  image: String,
});
</script>

<template>
  <div>
    <div
      class="w-47px h-47px rounded border overflow-hidden"
      style="cursor: zoom-in"
      @click="state.image_open = true"
    >
      <img :src="image" alt="" class="w-full h-full object-cover" />
    </div>
    <div
      class="fixed top-0 left-0 w-full h-full bg-[#00000044] z-1 items-center justify-center"
      :class="state.image_open ? 'flex' : 'hidden'"
    >
      <div
        class="absolute w-full h-full top-0 left-0"
        @click="() => (state.image_open = false)"
        style="cursor: zoom-out"
      ></div>
      <div
        class="w-700px h-700px bg-white rounded-lg shadow-2xl transition-all transform flex flex-col"
        :class="
          state.image_open ? 'scale-100 opacity-100' : 'scale-50 opacity-0'
        "
      >
        <div class="flex px-6 py-4 justify-between items-center">
          <strong>Image</strong>
          <button @click="() => (state.image_open = false)">
            <i class="ri-close-line text-2xl"></i>
          </button>
        </div>
        <div class="p-6 pt-0 w-full flex-1 flex h-0">
          <img
            :src="image"
            alt=""
            class="w-full h-full object-contain border"
          />
        </div>
      </div>
    </div>
  </div>
</template>
