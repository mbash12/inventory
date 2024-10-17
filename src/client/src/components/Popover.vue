<script setup>
import { onMounted, reactive } from "vue";
const props = defineProps({
    title: String,
    content: String,
});
const state = reactive({ show: false, top: null, left: null, origin: '0% 0%' });
const onclick = (e) => {
    let target = e.target.getBoundingClientRect();
    state.top = target.top + target.height;
    state.left = target.left + 300 > window.innerWidth ? window.innerWidth - 300 : target.left;
    state.origin = state.left === target.left ? '0% 0%' : `50% 0%`;
    setTimeout(() => {
        state.show = true;
    }, 10);
}
</script>
<template>
    <div class="relative">
        <div class="cursor-help"  @click="onclick">
            <slot />
        </div>
        <div
            class="fixed w-full h-full top-0 left-0 bg-[#00000011] z-10"
            @click.stop="state.show = false"
            v-if="state.show"
        ></div>
        <div
            class="fixed bg-white shadow-lg rounded-lg z-20  transition-all transform "
            :class="
                state.show
                    ? 'max-h-400px w-300px opacity-100 scale-100 p-3'
                    : 'max-h-0 w-0 opacity-30 scale-10 overflow-hidden p-0'
            "
            :style="{ top: state.top + 'px', left: state.left + 'px', transformOrigin: state.origin }"
        >
        <div class="flex flex-col gap-1">
            <strong class="text-sm">{{ title ?? 'title'}}</strong>
            <div class="text-xs text-gray-500" v-html="content">
            </div>
        </div>
        </div>
    </div>
</template>
