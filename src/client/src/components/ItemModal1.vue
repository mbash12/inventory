<script setup>
import { reactive, watch, computed} from "vue";

const emit = defineEmits(["action", "delete"]);
const props = defineProps({
  show: Boolean,
  items: Array,
  selected: Number,
  uoms: Array,
  taxes: Array,
});
const state = reactive({
  name: null,
  quantity: null,
  price: null,
  description: null,
  uom_code: null,
  tax_code: null,
});

// Calculate subtotal based on quantity and price
const subtotal = computed(() => {
  if (state.quantity && state.price) {
    return parseInt(state.quantity) * parseInt(state.price);
  }
  return 0;
});

watch(props, () => {
  if (props.show) {
    if (props.selected !== null) {
      let data = { ...props.items[props.selected] };
      state.name = data.name;
      state.quantity = data.quantity;
      state.price = data.price || null;
      state.description = data.description;
      state.uom_code = data.uom_code || null;
      state.tax_code = data.tax_code || null;
    } else {
      state.name = null;
      state.quantity = null;
      state.price = null;
      state.description = null;
      state.uom_code = null;
      state.tax_code = null;
    }
  }
});
const emitAction = () => {
  emit("action", {
    name: state.name,
    quantity: state.quantity,
    price: state.price,
    total_price: subtotal.value,
    description: state.description,
    uom_code: state.uom_code,
    tax_code: state.tax_code,
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
          <span class="text-sm text-blue-gray-400">Name *</span>
          <input type="text"
            class="w-full h-10 border-b bg-transparent text-sm text-black"
            v-model="state.name"
            required
          />
        </label>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex flex-col items-start">
            <span class="text-sm text-blue-gray-400">Quantity *</span>
            <input
              type="number"
              class="border-b w-full h-10 bg-transparent text-sm text-black"
              v-model="state.quantity"
              required
            />
          </label>
          <label class="flex flex-col items-start">
            <span class="text-sm text-blue-gray-400">Price *</span>
            <input
              type="number"
              class="border-b w-full h-10 bg-transparent text-sm text-black"
              v-model="state.price"
              required
            />
          </label>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex flex-col items-start">
            <span class="text-sm text-blue-gray-400">UOM</span>
            <select
              class="border-b w-full h-10 bg-transparent text-sm text-black"
              v-model="state.uom_code"
            >
              <option value="">Select UOM</option>
              <option
                v-for="uom in props.uoms"
                :key="uom.id"
                :value="uom.code"
              >
                {{ uom.name }} ({{ uom.code }})
              </option>
            </select>
          </label>
          <label class="flex flex-col items-start">
            <span class="text-sm text-blue-gray-400">Tax</span>
            <select
              class="border-b w-full h-10 bg-transparent text-sm text-black"
              v-model="state.tax_code"
            >
              <option value="">Select Tax</option>
              <option
                v-for="tax in props.taxes"
                :key="tax.id"
                :value="tax.code"
              >
                {{ tax.name }} ({{ tax.tax_percentage }}%) ({{ tax.code }})
              </option>
            </select>
          </label>
        </div>
        <div class="flex flex-col">
          <span class="text-sm text-blue-gray-400">Subtotal</span>
          <div class="border-b w-full h-10 bg-transparent text-sm text-black flex items-center">
            {{ subtotal }}
          </div>
        </div>
        <div class="flex w-full">
          <label class="flex flex-col items-start relative w-full">
            <span class="text-sm text-blue-gray-400">Description</span>
            <textarea
              class="border-b w-full h-20 bg-transparent text-sm text-black pr-20 pt-2"
              v-model="state.description"
            >
            </textarea>
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
