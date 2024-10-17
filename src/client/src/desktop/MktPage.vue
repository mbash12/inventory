<script setup>
import { onMounted, reactive } from "vue";
import MktLayout from "./MktLayout.vue";
</script>

<template>
    <MktLayout>
        <RouterView></RouterView>
    </MktLayout>
</template>

<style>
input.noicon::-webkit-inner-spin-button,
input.noicon::-webkit-calendar-picker-indicator {
    display: none;
    -webkit-appearance: none;
}
</style>
