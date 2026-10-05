<script setup>
defineProps({ page: Number, total: Number });
defineEmits(["prev", "next"]);
</script>

<template>
    <div class="h-40px bg-gray-100 shadow-lg w-full border-t flex items-center px-1">
        <div
            class="px-4 h-35px rounded border flex items-center justify-center bg-white"
            :class="page <= 1 ? 'filter brightness-90 cursor-default text-gray-400' : 'hover:bg-gray-50'"
            @click="page > 1 && $emit('prev')"
        >
            <i class="ri-skip-left-line"></i>
            <span class="text-xs">Prev</span>
        </div>
        <div class="flex-1 items-center justify-center flex text-sm">
            Page {{ page }} of {{ total || 1 }}
        </div>
        <div
            class="px-4 h-35px rounded border flex items-center justify-center bg-white"
            :class="page >= (total || 1) ? 'filter brightness-90 cursor-default text-gray-400' : 'hover:bg-gray-50'"
            @click="page < (total || 1) && $emit('next')"
        >
            <span class="text-xs">Next</span>
            <i class="ri-skip-right-line"></i>
        </div>
    </div>
</template>
