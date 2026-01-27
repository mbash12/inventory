<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
    title: String,
    back: String,
});
const router = useRouter()
const goBack = () => {
    window.history.back()
}
</script>
<template>
    <div class="flex h-14 border-b items-center p-4 gap-4">
        <div>
            <div class="reset w-6 h-6 flex items-center justify-center text-lg" @click="$emit('toggle')">
                <i class="ri-menu-fill"></i>
            </div>
        </div>
        <strong class="text-black">{{ title || "title" }}</strong>
    </div>
</template>
