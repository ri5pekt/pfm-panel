<!-- App.vue -->
<template>
    <div class="pfm-panel-app">
        <div class="top-bar">
            <n-tabs type="line" :value="currentTab" @update:value="navigate" class="top-tabs">
                <n-tab name="orders" :tab="renderTab('Orders', 'orders')" />
                <n-tab name="subscriptions" :tab="renderTab('Subscriptions', 'subscriptions')" />
                <n-tab name="customers" :tab="renderTab('Customers', 'customers')" />
                <n-tab name="replacements" :tab="renderTab('Replacement Orders', 'replacements')" />
                <n-tab name="coupons" :tab="renderTab('Coupons', 'coupons')" />
                <n-tab name="stats" :tab="renderTab('Statistics', 'stats')" />
                <n-tab name="reports" :tab="renderTab('Reports', 'reports')" />
                <n-tab
                    v-if="hasAdminRights"
                    name="admin-activity"
                    :tab="renderTab('Admin Activity', 'admin-activity')"
                />
            </n-tabs>
            <div class="greeting">👋 Hello, {{ fullName }}!</div>
        </div>

        <WorkTabsBar
            v-if="activeWorkShowBar"
            :value="activeWork.activeKey"
            :tabs="activeWork.tabs"
            @change="activeWork.onChange"
            @close="activeWork.onClose"
        />

        <RouterView v-slot="{ Component, route }">
            <Transition :name="transitionName" mode="out-in" appear>
                <KeepAlive :max="30">
                    <component :is="Component" :key="viewKey(route)" />
                </KeepAlive>
            </Transition>
        </RouterView>
    </div>
</template>

<script setup>
import { useRouter, useRoute } from "vue-router";
import { ref, watch, h, computed } from "vue";
import { can as pfmCan } from "@/utils/permissions";
import { useOrderWorkTabs } from "@/composables/useOrderWorkTabs";
import { useSubscriptionWorkTabs } from "@/composables/useSubscriptionWorkTabs";
import { useCustomerWorkTabs } from "@/composables/useCustomerWorkTabs";
import { useReplacementWorkTabs } from "@/composables/useReplacementWorkTabs";
import { useRoutedWorkTabs } from "@/composables/useRoutedWorkTabs";
import WorkTabsBar from "@/components/ui-elements/WorkTabsBar.vue";

const router = useRouter();
const route = useRoute();
const fullName = window.PFMPanelData?.user?.full_name || "Admin";
const roles = window.PFMPanelData?.user?.roles || [];
const hasAdminRights = pfmCan("admin_rights");
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
    "admin-activity": "admin-activity",
    coupons: "coupons",
    "coupon-view": "coupons",
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

// --- Orders work tabs (list + multiple open order views) ---
const orderStore = useOrderWorkTabs();
const subscriptionStore = useSubscriptionWorkTabs();
const customerStore = useCustomerWorkTabs();
const replacementStore = useReplacementWorkTabs();

const orderWork = useRoutedWorkTabs({
    route,
    router,
    listRouteName: "orders",
    viewRouteName: "order-view",
    routeParam: "id",
    store: {
        tabs: orderStore.tabs,
        activeKey: orderStore.activeKey,
        mainKey: orderStore.mainKey,
        keyForId: orderStore.keyForOrderId,
        idFromKey: orderStore.orderIdFromKey,
        open: orderStore.openOrder,
        close: orderStore.closeTab,
        setActive: orderStore.setActiveKey,
    },
});

const subscriptionWork = useRoutedWorkTabs({
    route,
    router,
    listRouteName: "subscriptions",
    viewRouteName: "subscription-view",
    routeParam: "id",
    store: {
        tabs: subscriptionStore.tabs,
        activeKey: subscriptionStore.activeKey,
        mainKey: subscriptionStore.mainKey,
        keyForId: subscriptionStore.keyForSubscriptionId,
        idFromKey: subscriptionStore.subscriptionIdFromKey,
        open: subscriptionStore.openSubscription,
        close: subscriptionStore.closeTab,
        setActive: subscriptionStore.setActiveKey,
    },
});

const customerWork = useRoutedWorkTabs({
    route,
    router,
    listRouteName: "customers",
    viewRouteName: "customer-view",
    routeParam: "id",
    store: {
        tabs: customerStore.tabs,
        activeKey: customerStore.activeKey,
        mainKey: customerStore.mainKey,
        keyForId: customerStore.keyForCustomerId,
        idFromKey: customerStore.customerIdFromKey,
        open: customerStore.openCustomer,
        close: customerStore.closeTab,
        setActive: customerStore.setActiveKey,
    },
});

const replacementWork = useRoutedWorkTabs({
    route,
    router,
    listRouteName: "replacements",
    viewRouteName: "replacement-view",
    routeParam: "id",
    store: {
        tabs: replacementStore.tabs,
        activeKey: replacementStore.activeKey,
        mainKey: replacementStore.mainKey,
        keyForId: replacementStore.keyForReplacementId,
        idFromKey: replacementStore.replacementIdFromKey,
        open: replacementStore.openReplacement,
        close: replacementStore.closeTab,
        setActive: replacementStore.setActiveKey,
    },
});

// --- Only show one work-tab bar (the current section) ---
const activeSection = computed(() => currentTab.value);
const activeWork = computed(() => {
    switch (activeSection.value) {
        case "orders":
            return orderWork;
        case "subscriptions":
            return subscriptionWork;
        case "customers":
            return customerWork;
        case "replacements":
            return replacementWork;
        default:
            return null;
    }
});
const activeWorkShowBar = computed(() => {
    // showBar is a computed ref returned from useRoutedWorkTabs; unwrap explicitly to avoid "nested ref" truthiness.
    return !!activeWork.value?.showBar?.value;
});

// --- Close all other tab groups when switching section ---
watch(
    activeSection,
    (next) => {
        // When leaving a section, clear its open work tabs so only the current section stays "alive".
        // If we switch to a non-work-tab section (reports/stats/coupons/admin), clear all work tabs.
        const clearOrders = next !== "orders";
        const clearSubs = next !== "subscriptions";
        const clearCustomers = next !== "customers";
        const clearRepl = next !== "replacements";

        if (clearOrders) orderStore.clearAll();
        if (clearSubs) subscriptionStore.clearAll();
        if (clearCustomers) customerStore.clearAll();
        if (clearRepl) replacementStore.clearAll();
    },
    { flush: "sync" }
);

const transitionName = ref("fade-slide");
function viewKey(r) {
    const paramsKey = JSON.stringify(r.params || {});
    return `${r.name}:${paramsKey}`;
}
</script>
