<script setup>
import { onMounted, reactive, ref, watch, toRefs } from "vue";
const props = defineProps({
    pages: Array,
    page: Number,
});
</script>
<template>
    <div class="flex border-t">
        <button
            v-for="(item, i) in pages"
            @click="$emit('setpage', i)"
            :class="`reset flex-1 flex flex-col p-2 items-center justify-between    ${
                page == i ? 'text-red-500 ' : 'text-black'
            }`"
            :key="i"
        >
            <i
                :class="` text-xl ri-${
                    item.icon + (page == i ? '-fill' : '-line')
                }`"
            ></i>
            <span class="text-xs">{{ item.label }}</span>
        </button>
    </div>
</template>
