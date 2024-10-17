<script setup>
import { onMounted, reactive, watch } from "vue";
import {
    getClientList,
    getShippingVendorList,
    getWarehouseList,
} from "../services/service";
const emit = defineEmits(["select"]);
const props = defineProps({
    value: String | Number,
    alias: String | Number,
    required: String,
    disabled: Boolean,
});
const state = reactive({
    focus: false,
    search: null,
    list: [],
});
const init = () => {
    if (props.value) {
        getShippingVendorList({ find: props.value }).then((r) => {
            if (r.code == 200) {
                emit(
                    "select",
                    r.data.map((e) => ({ value: e.id, label: e.name }))[0]
                );
            }
        });
    }
};
onMounted(() => {
    getList().then(()=>{
        setTimeout(() => {
            init();
        }, 1000);
    })
});
const getList = () => {
    return new Promise((resolve, reject) => {
        let filter = {};
        if (state.search) filter.search = state.search;
        getShippingVendorList(filter).then((r) => {
            if (r.code == 200) {
                state.list = r.data.map((e) => ({ value: e.id, label: e.name }));
                resolve()
            }
        });
    })
};
const asdasd = (item) => {
    emit("select", item);
    // setTimeout(() => {
    //     state.label = state.list.find((e) => e.value == props.value)?.label;
    // }, 10);
};
let waiter;
watch(
    () => state.search,
    () => {
        clearTimeout(waiter);
        waiter = setTimeout(getList, 500);
    }
);
</script>
<template>
    <div class="relative w-full h-full rounded-lg">
        <div class="w-full h-10 relative border-b flex items-center">
            <div class="w-full text-sm truncate max-w-full py-6px">
                {{ props.alias }}
            </div>
            <input
                :title="props.alias"
                type="text"
                class="w-full h-full bg-white absolute top-0 left-0 text-sm truncate max-w-full border-b"
                @focus="state.focus = true"
                @blur="(state.focus = false), (state.search = null)"
                :disabled="props.disabled"
                :placeholder="props.alias"
                v-model="state.search"
                :class="state.focus ? 'opacity-100' : 'opacity-0'"
            />
        </div>
        <div
            class="absolute w-full max-h-300px bg-white z-2 top-50px shadow-lg rounded border overflow-auto flex flex-col"
            v-if="state.focus"
        >
            <div
                class="w-full py-2 px-4 text-sm hover:bg-gray-50 bg-white"
                v-if="state.list.length > 0"
                v-for="(item, i) in state.list"
                @mousedown="asdasd(item)"
            >
                {{ item.label }}
            </div>
            <div
                class="w-full py-2 px-4 text-sm text-center text-gray-500"
                v-else
            >
                Not Found
            </div>
        </div>
        <input
            type="hidden"
            required
            v-if="required == 'true' && value == null"
            name="hidden"
        />
    </div>
</template>
