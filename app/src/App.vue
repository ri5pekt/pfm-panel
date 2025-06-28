
<!-- App.vue -->
<template>
    <div class="pfm-panel-app">
        <div class="top-bar">
            <n-tabs type="line" :value="currentTab" @update:value="navigate" class="top-tabs">
                <n-tab name="orders" tab="Orders" />
                <n-tab name="subscriptions" tab="Subscriptions" />
                <n-tab name="customers" tab="Customers" />
                <n-tab name="stats" tab="Statistics" />
            </n-tabs>
            <div class="greeting">👋 Hello, {{ fullName }}!</div>
        </div>

        <router-view />
    </div>
</template>

<script setup>
import { useRouter, useRoute } from "vue-router";
import { ref, watch } from "vue";

const router = useRouter();
const route = useRoute();
const fullName = window.PFMPanelData?.user?.full_name || "Admin";
const roles = window.PFMPanelData?.user?.roles || [];
console.log("🛂 Logged-in user roles:", roles);

const tabMap = {
    orders: "orders",
    "order-view": "orders",
    subscriptions: "subscriptions",
    "subscription-view": "subscriptions",
    customers: "customers",
    "customer-view": "customers",
    stats: "stats",
};

const currentTab = ref(tabMap[route.name] || "orders");

watch(
    () => route.name,
    (newName) => {
        currentTab.value = tabMap[newName] || "orders";
    }
);

function navigate(name) {
    router.push({ name });
}
</script>
