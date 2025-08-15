<!-- App.vue -->
<template>
    <div class="pfm-panel-app">
        <div class="top-bar">
            <n-tabs type="line" :value="currentTab" @update:value="navigate" class="top-tabs">
                + <n-tab name="orders" :tab="renderTab('Orders', 'orders')" /> +
                <n-tab name="subscriptions" :tab="renderTab('Subscriptions', 'subscriptions')" /> +
                <n-tab name="customers" :tab="renderTab('Customers', 'customers')" /> +
                <n-tab name="replacements" :tab="renderTab('Replacement Orders', 'replacements')" /> +
                <n-tab name="stats" :tab="renderTab('Statistics', 'stats')" /> +
                <n-tab name="reports" :tab="renderTab('Reports', 'reports')" /> +
            </n-tabs>
            <div class="greeting">👋 Hello, {{ fullName }}!</div>
        </div>

        <RouterView v-slot="{ Component, route }">
            <Transition :name="transitionName" mode="out-in" appear>
                <component :is="Component" :key="viewKey(route)" />
            </Transition>
        </RouterView>
    </div>
</template>

<script setup>
import { useRouter, useRoute } from "vue-router";
import { ref, watch, h } from "vue";

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
    replacements: "replacements",
    "replacement-view": "replacements",
    reports: "reports",
    "report-view": "reports",
};

const currentTab = ref(tabMap[route.name] || "orders");

watch(
    () => route.name,
    (newName) => {
        currentTab.value = tabMap[newName] || "orders";
    }
);

function navigate(name) {
    if (route.name !== name) router.push({ name });
}

function onTabClick(tabName) {
    // If you click the already-selected tab (e.g., you're on order-view and tab shows "orders"),
    // force navigation to the list route.
    if (currentTab.value === tabName) {
        router.push({ name: tabName });
    }
}

function renderTab(label, name) {
    // Use a vnode so we can capture clicks even when the tab is already selected.
    return h("span", { style: "cursor: pointer;", onClick: () => onTabClick(name) }, label);
}

const transitionName = ref("fade-slide");
function viewKey(r) {
    const paramsKey = JSON.stringify(r.params || {});
    return `${r.name}:${paramsKey}`;
}
</script>
