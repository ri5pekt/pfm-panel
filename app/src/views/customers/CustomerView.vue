<!-- CustomerView.vue -->
<template>
    <div class="customer-view">
        <div class="page-top">
            <n-button @click="backToCustomers">← Back to Customers</n-button>
            <div class="page-title">
                {{ fullName || "Loading..." }}
            </div>
        </div>
        <div class="customer-grid">
            <!-- Panels -->
            <CustomerInfoPanel :customer="customer" :loading="loading" @updateCustomer="fetchCustomer" />
            <PastOrdersPanel :customerId="customerId" />
            <CustomerSubscriptionsPanel :customerId="customerId" />
            <YotpoPanel :customerId="customerId" source="loyalty" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";
import { useCustomerWorkTabs } from "@/composables/useCustomerWorkTabs";

// Panels (create these as needed)
import CustomerInfoPanel from "@/components/customer-view-panels/CustomerInfoPanel.vue";
import PastOrdersPanel from "@/components/order-view-panels/PastOrdersPanel.vue";
import CustomerSubscriptionsPanel from "@/components/customer-view-panels/CustomerSubscriptionsPanel.vue";
import YotpoPanel from "@/components/customer-view-panels/YotpoPanel.vue";

const props = defineProps({
    id: String,
});

const router = useRouter();
const message = useMessage();

const { closeTab, keyForCustomerId, mainKey, setActiveKey } = useCustomerWorkTabs();

const customerId = computed(() => Number(props.id));

const customer = ref(null);
const loading = ref(false);

async function fetchCustomer() {
    loading.value = true;
    try {
        console.log("🔍 Fetching customer with ID:", customerId.value);
        const json = await request({
            url: `/customers/${customerId.value}`,
            method: "GET",
        });
        customer.value = json;
        console.log("✅ Customer loaded:", json);
        console.log("🧩 Customer meta:", json?.meta_data ?? json?.meta ?? null);
    } catch (err) {
        message.error("Failed to load customer");
        console.error(err);
    } finally {
        loading.value = false;
    }
}

const fullName = computed(() => {
    if (!customer.value) return "";
    const first = customer.value.first_name || "";
    const last = customer.value.last_name || "";
    return [first, last].filter(Boolean).join(" ") || customer.value.name || "";
});

onMounted(() => {
    fetchCustomer();
});

// Reactively re-fetch when customer ID changes (e.g. navigating between /customers/:id routes)
watch(
    customerId,
    () => {
        fetchCustomer();
    },
    { immediate: false }
);

function backToCustomers() {
    if (props.id) closeTab(keyForCustomerId(props.id));
    setActiveKey(mainKey());
    router.push({ name: "customers" });
}
</script>
