<template>
    <div class="customer-view">
        <div class="page-top">
            <n-button @click="router.back()">← Back to Customers</n-button>
            <div class="page-title">
                {{ fullName || "Loading..." }}
            </div>
        </div>
        <div class="customer-grid">
            <!-- Panels -->
            <CustomerInfoPanel :customer="customer" :loading="loading" @updateCustomer="fetchCustomer" />
            <PastOrdersPanel :customerId="customerId" />
            <CustomerSubscriptionsPanel :customerId="customerId" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";

// Panels (create these as needed)
import CustomerInfoPanel from "@/components/customer-view-panels/CustomerInfoPanel.vue";
import PastOrdersPanel from "@/components/order-view-panels/PastOrdersPanel.vue";
import CustomerSubscriptionsPanel from "@/components/customer-view-panels/CustomerSubscriptionsPanel.vue";

const route = useRoute();
const router = useRouter();
const message = useMessage();

const customerId = computed(() => Number(route.params.id));

const customer = ref(null);
const loading = ref(false);

async function fetchCustomer() {
    loading.value = true;
    try {
        customer.value = await request({
            url: `/customers/${customerId.value}`,
            method: "GET",
        });
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
</script>
