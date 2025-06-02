<template>
    <div class="order-view">
        <div class="page-title">Order #{{ props.id }}</div>
        <n-button @click="router.back()">← Back to Orders</n-button>

        <div class="order-grid">
            <!-- Order Info Panel -->
            <OrderInfoPanel :order="order" :loading="loadingOrder" @update-order="fetchOrder" :getMeta="getMeta" />

            <!-- Order Notes Panel -->
            <OrderNotesPanel :orderId="props.id" />

            <!-- Order Totals Panel -->
            <OrderTotalsPanel :order="order" :orderId="props.id" :loading="loadingOrder" @updateOrder="fetchOrder" />

            <!-- Past Orders Panel -->
            <PastOrdersPanel :customerId="order?.customer_id" :excludeOrderId="Number(props.id)" />

            <!-- Warehouse Export Panel -->
            <WarehouseExportPanel :loading="loadingOrder" :getMeta="getMeta" :trackingNumber="trackingNumber" />
        </div>
    </div>
</template>

<script setup>
import { useOrder } from "@/composables/useOrder";
import { ref, computed, onMounted, watch, h } from "vue";
import { useRoute, useRouter } from "vue-router";
import { apiBase, apiBaseCustom, authHeader } from "@/utils/api";
import { formatOrderDate, formatCurrency, setCurrency } from "@/utils/utils";
import { useMessage } from "naive-ui";

// Panles
import OrderNotesPanel from "@/components/order-view-panels/OrderNotesPanel.vue";
import OrderInfoPanel from "@/components/order-view-panels/OrderInfoPanel.vue";
import PastOrdersPanel from "@/components/order-view-panels/PastOrdersPanel.vue";
import WarehouseExportPanel from "@/components/order-view-panels/WarehouseExportPanel.vue";
import OrderTotalsPanel from "@/components/order-view-panels/OrderTotalsPanel.vue";

const props = defineProps({
    id: String,
});

const { order, loadingOrder, fetchOrder, formattedCreatedDate, getMeta, trackingNumber } = useOrder(props.id);


const message = useMessage();
const route = useRoute();
const router = useRouter();



watch(
    () => order.value?.currency,
    (currency) => {
        if (currency) setCurrency(currency);
    },
    { immediate: true }
);



</script>
