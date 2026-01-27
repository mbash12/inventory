<script setup>
import { onMounted, reactive } from "vue";
import Layout from "./Layout.vue";
</script>

<template>
    <Layout>
        <RouterView></RouterView>
    </Layout>
</template>

<style>
input.noicon::-webkit-inner-spin-button,
input.noicon::-webkit-calendar-picker-indicator {
    display: none;
    -webkit-appearance: none;
}
</style>
