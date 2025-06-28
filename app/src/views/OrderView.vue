<!-- OrderView.vue -->
<template>
    <div class="order-view">
        <div class="page-top">
            <n-button @click="router.back()">← Back to Orders</n-button>
            <div class="page-title">Order #{{ props.id }}</div>
            
        </div>
        <div class="order-grid">
            <!-- Order Info Panel -->
            <OrderInfoPanel
                :order="order"
                :loading="loadingOrder"
                @update-order="handleOrderUpdate"
                :getMeta="getMeta"
            />

            <!-- Order Notes Panel -->
            <OrderNotesPanel :orderId="props.id" :refreshKey="notesRefreshKey" />

            <!-- Order Totals Panel -->
            <OrderTotalsPanel
                :order="order"
                :orderId="props.id"
                :loading="loadingOrder"
                @updateOrder="handleOrderUpdate"
            />

            <!-- Past Orders Panel -->
            <PastOrdersPanel :customerId="customerId" :excludeOrderId="Number(props.id)" :key="order?.id" />

            <!-- Warehouse Export Panel -->
            <WarehouseExportPanel
                :loading="loadingOrder"
                :getMeta="getMeta"
                :trackingNumber="trackingNumber"
                :orderId="props.id"
                @updateOrder="handleOrderUpdate"
            />

            <!-- Subscriptions Panel -->
            <SubscriptionPanel :branch="order?.subscription_branch" />
        </div>
    </div>
</template>

<script setup>
import { useOrder } from "@/composables/useOrder";
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMessage } from "naive-ui";

// Panles
import OrderNotesPanel from "@/components/order-view-panels/OrderNotesPanel.vue";
import OrderInfoPanel from "@/components/order-view-panels/OrderInfoPanel.vue";
import PastOrdersPanel from "@/components/order-view-panels/PastOrdersPanel.vue";
import WarehouseExportPanel from "@/components/order-view-panels/WarehouseExportPanel.vue";
import OrderTotalsPanel from "@/components/order-view-panels/OrderTotalsPanel.vue";
import SubscriptionPanel from "@/components/order-view-panels/SubscriptionPanel.vue";

const props = defineProps({
    id: String,
});

const orderId = computed(() => props.id);

const { order, loadingOrder, fetchOrder, formattedCreatedDate, getMeta, trackingNumber } = useOrder(orderId);

const customerId = computed(() => order.value?.customer_id || null);

const notesRefreshKey = ref(0);
function refreshNotes() {
    notesRefreshKey.value = Date.now();
}

function handleOrderUpdate() {
    fetchOrder();
    refreshNotes();
}

const message = useMessage();
const route = useRoute();
const router = useRouter();
</script>
