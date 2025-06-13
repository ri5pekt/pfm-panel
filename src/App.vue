<template>
    <div class="pfm-panel-app">
        <n-tabs type="line" :value="currentTab" @update:value="navigate" class="top-tabs">
            <n-tab name="orders" tab="Orders" />
            <n-tab name="stats" tab="Statistics" />
        </n-tabs>

        <router-view />
    </div>
</template>

<script setup>
import { useRouter, useRoute } from "vue-router";
import { ref, watch } from "vue";

const router = useRouter();
const route = useRoute();

const currentTab = ref(route.name || "orders");

watch(
    () => route.name,
    (newName) => {
        currentTab.value = newName;
    }
);

function navigate(name) {
    router.push({ name });
}
</script>

<style scoped>
.top-tabs {
    margin-bottom: 20px;
}
</style>
