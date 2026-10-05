<script setup>
defineProps({ modelValue: String, placeholder: String, filtered: Boolean, noFilter: Boolean });
defineEmits(["update:modelValue", "search", "filter"]);
</script>

<template>
    <div class="flex shadow-md z-2 shadow-gray-100 bg-white px-2 py-1 gap-1">
        <form
            @submit.prevent="$emit('search')"
            class="flex-1 flex items-center rounded-full overflow-hidden bg-gray-100 relative"
        >
            <input
                type="search"
                :placeholder="placeholder || 'Search...'"
                class="h-9 flex-1 text-light bg-transparent text-black rounded-full px-4 w-full"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                class="h-9 w-9 flex items-center justify-center text-app-500 absolute right-1"
            >
                <i class="ri-search-line"></i>
            </button>
        </form>
        <button
            v-if="!noFilter"
            class="h-9 w-9 flex items-center justify-center relative"
            :class="filtered ? 'text-app-900' : 'text-app-500'"
            @click="$emit('filter')"
        >
            <i class="ri-filter-line"></i>
            <span
                v-if="filtered"
                class="absolute top-1 right-1 w-2 h-2 rounded-full bg-app-500"
            ></span>
        </button>
        <slot />
    </div>
</template>
