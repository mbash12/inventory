<script setup>
import { reactive, watch} from "vue";
import Upload from "./Upload.vue";
const emit = defineEmits(["hide","action"]);
const props = defineProps({
  show: Boolean,
  files: Array,
});
const emitAction = (files) => {
  emit("action", files);
};
</script>
<template>
  <Transition name="sheet">
    <div
      class="w-full h-full  fixed top-0 left-0 bg-[#00000022] flex items-center justify-center p-4 transition z-10"
      v-if="show"
    >
      <form
        @submit.prevent="emitAction"
        class="w-full p-4 rounded-lg bg-white shadow-lg flex flex-col gap-4 transition modalbody max-w-500px"
      >
      <span class="mb-4 font-bold text-xl">Upload Approved Design Files</span>
      <Upload :files="props.files" @update="emitAction" />
       
        <div class="flex mt-2 gap-2 justify-end">
          <button
            type="button"
            class="px-4 py-2 text-left flex items-center justify-center gap-4 bg-gray-100 rounded mla"
            @click="$emit('hide')"
          >
            <span class=""> Close </span>
          </button>
        </div>
      </form>
    </div>
  </Transition>
</template>

<style>
.sheet-enter-from .modalbody,
.sheet-leave-to .modalbody {
  opacity: 0;
  transform: translateY(100%);
}
</style>
