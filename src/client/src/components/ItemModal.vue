<script setup>
import { reactive, watch} from "vue";

const emit = defineEmits(["action","hide", "delete"]);
const props = defineProps({
  show: Boolean,
  items: Array,
  misc: Object,
  selected: Number,
  default_origin: Number,
});
const state = reactive({
  origin: null,
  product: null,
  quantity: null,
  delivered_at: null,
});

watch(props, () => {
  if (props.show) {
    if (props.selected !== null) {
      let data = { ...props.items[props.selected] };
      state.origin = data.origin;
      state.product = data.product;
      state.quantity = data.quantity;
      state.delivered_at = data.delivered_at;
    } else {
      state.origin = props.default_origin;
      state.product = null;
      state.quantity = null;
      state.delivered_at = null;
    }
  }
});
const emitAction = () => {
  emit("action", {
    origin: state.origin,
    product: state.product,
    quantity: state.quantity,
    delivered_at: state.delivered_at,
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
        <strong>{{ selected !== null ? "Edit Item" : "Add Item" }}</strong>
        <label class="flex flex-col items-start">
          <span class="text-sm text-blue-gray-400">Origin *</span>
          <select
            class="w-full h-10 border-b bg-transparent text-sm text-black"
            v-model="state.origin"
            required
          >
            <option value="" disabled selected>Select origin</option>
            <option
              v-for="(option, i) in misc.warehouses.filter((e) => e.id !== 2)"
              :value="option.id"
              :key="i"
            >
              {{ option.name }}
            </option>
          </select>
        </label>
        <label class="flex flex-col items-start">
          <span class="text-sm text-blue-gray-400">Item name *</span>
          <select
            class="w-full h-10 border-b bg-transparent text-sm text-black"
            v-model="state.product"
            :disabled="state.origin === null"
            required
          >
            <option value="" disabled selected>Select item</option>
            <option
              v-for="(option, i) in misc.products"
              :value="option.id"
              :key="i"
            >
              {{ option.name }}
            </option>
          </select>
        </label>
        <div class="flex w-full">
          <label class="flex flex-col items-start relative w-full">
            <span class="text-sm text-blue-gray-400">Quantity *</span>
            <input
              type="number"
              class="border-b w-full h-10 bg-transparent text-sm text-black pr-20"
              :disabled="state.origin === null || state.product === null"
              v-model="state.quantity"
              required
            />
            <div class="absolute right-2 bottom-2">
              /
              {{
                misc.inventories.find(
                  (e) =>
                    e.warehouse === state.origin && e.product === state.product
                )?.quantity ?? 0
              }}
            </div>
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
            class="px-4 py-2 text-left flex items-center justify-center gap-4 w-1/2 bg-gray-100 rounded"
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
