<script setup>
import { reactive, watch} from "vue";

const emit = defineEmits(["bulkadd","hide"]);
const props = defineProps({
  show: Boolean,
  items: Array,
  misc: Object,
  default_origin: Number,
});
const state = reactive({
  origin: null,
  products: null,
});

watch(props, () => {
  if (props.show) {
      state.origin = props.default_origin;
      state.products = props.misc?.products?.map(e=>{ return { ...e, qty: props.misc?.inventories.find(f => f.warehouse === state.origin && f.product === e.id)?.quantity ?? 0, max : props.misc?.inventories.find(f => f.warehouse === state.origin && f.product === e.id)?.quantity ?? 0  }}).filter(e=>e.qty > 0);
  }
});
const emitAction = () => {
  emit("bulkadd", {
    origin: state.origin,
    products: state.products
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
        <strong>Add Bulk Item</strong>
        <span class="text-sm text-gray-500 -mt-2">Isi qty 0 untuk mengecualikan</span>
        <div style="height:calc(100vh - 300px)" class="overflow-auto">
          <template v-for="(i, index) in state?.products">
            <div class="flex gap-4 items-center">
              <div class="text-sm flex-1"  :class="{'line-through text-gray-300' : i.qty === 0}">
                  {{ i.name }}
              </div>
              <div class="flex items-center">
                  <input type="number"  class="border-b w-full w-50px h-10 bg-transparent text-sm text-black min-w-0" v-model="state.products[index].qty" :max="i.max" required>
                  <span class="ml-2 w-50px text-sm">/ {{ i.max }}</span>
              </div>
            </div>
          </template>
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
