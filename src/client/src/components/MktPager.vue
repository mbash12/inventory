<script setup>
defineProps({ page: Number, total: Number });
defineEmits(["prev", "next"]);
</script>

<template>
    <div class="flex justify-between px-6 py-2 items-center">
        <button
            class="text-sm border rounded-md px-4 py-2 bg-white"
            :class="page <= 1 ? 'filter brightness-90 cursor-default text-gray-400' : 'hover:bg-gray-50'"
            @click="page > 1 && $emit('prev')"
        >
            Previous
        </button>
        <div class="text-sm">Page {{ page }} of {{ total || 1 }}</div>
        <button
            class="text-sm border rounded-md px-4 py-2 bg-white"
            :class="page >= (total || 1) ? 'filter brightness-90 cursor-default text-gray-400' : 'hover:bg-gray-50'"
            @click="page < (total || 1) && $emit('next')"
        >
            Next
        </button>
    </div>
</template>
