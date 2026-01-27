<script setup>
import { onMounted, reactive, watch } from "vue";
import { getClientList } from "../services/service";
const emit = defineEmits(["select"]);
const props = defineProps({
    value: String | Number,
    required: String,
    disabled:Boolean
});
const state = reactive({
    focus: false,
    search: null,
    list: [],
});
onMounted(() => {
    getList();
});
const getList = () => {
    let filter = {};
    if (state.search) filter.search = state.search;
        getClientList(filter).then((r) => {
        if (r.code == 200) {
            state.list = r.data.map((e) => ({ value: e.id, label: e.name }));
        }
    });
};
const asdasd = (item) => {
    emit("select", item.label);

};
let waiter
watch(
    () => state.search,
    () => {
        clearTimeout(waiter)
        waiter = setTimeout(getList, 500);    
    }
);
</script>
<template>
    <div class="relative w-full h-full rounded-lg">
        <div class="w-full h-10 relative border-b flex items-center">
            <div class="w-full  text-sm truncate max-w-full py-6px" >
                {{ props.value }}
            </div>
            <input
                :title="props.value"
                type="text"
                class="w-full h-full bg-white absolute top-0 left-0 text-sm truncate max-w-full border-b" 
                @focus="state.focus = true"
                :disabled="props.disabled"
                @blur="state.focus = false, state.search = null"
                :placeholder="props.value"
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
            <div class="w-full py-2 px-4 text-sm text-center text-gray-500 " v-else>
                 Not Found
            </div>
        </div>
        <input type="hidden" required v-if="required == 'true' && value == null"  name="hidden"/>
    </div>
</template>
