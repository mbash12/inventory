<script setup>
defineProps({ text: String });
</script>

<template>
    <div class="w-full h-full flex flex-col justify-center items-center bg-gray-50 gap-2">
        <i class="ri-inbox-line text-100px text-gray-200"></i>
        <span class="text-sm font-medium text-gray-400">{{ text }}</span>
    </div>
</template>
