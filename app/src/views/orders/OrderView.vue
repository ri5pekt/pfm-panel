<!-- OrderView.vue -->
<template>
    <div class="order-view">
        <div class="page-top">
            <n-button @click="router.back()">← Back to Orders</n-button>
            <div class="page-title">
                Order #{{ props.id }}
                <n-tag v-if="isArchived" type="warning" size="small" style="margin-left: 8px; vertical-align: middle">
                    Archived
                </n-tag>
            </div>
            <div class="buttons-top">
                <n-button @click="createReplacement">Create replacement</n-button>
                <n-button v-if="!isArchived" @click="resendEmail('processing')"> Resend order email </n-button>
            </div>
        </div>
        
        <div class="order-grid">
            <!-- Order Info Panel -->
            <OrderInfoPanel
                :order="order"
                :loading="loadingOrder"
                @update-order="handleOrderUpdate"
                :getMeta="getMeta"
                :isArchived="isArchived"
            />

            <!-- Order Notes Panel -->
            <OrderNotesPanel v-if="!isArchived" :orderId="props.id" :refreshKey="notesRefreshKey" />

            <!-- Order Totals Panel -->
            <OrderTotalsPanel
                v-if="!isArchived"
                :order="order"
                :orderId="props.id"
                :loading="loadingOrder"
                @updateOrder="handleOrderUpdate"
            />

            <!-- Past Orders Panel -->
            <PastOrdersPanel
                v-if="!isArchived"
                :customerId="customerId"
                :excludeOrderId="Number(props.id)"
                :key="order?.id"
            />

            <!-- Warehouse Export Panel -->
            <WarehouseExportPanel
                v-if="!isArchived"
                :loading="loadingOrder"
                :getMeta="getMeta"
                :trackingNumber="trackingNumber"
                :orderId="props.id"
                @updateOrder="handleOrderUpdate"
            />

            <!-- Subscriptions Panel -->
            <SubscriptionPanel v-if="!isArchived" :branch="order?.subscription_branch" />

            <KountPanel :loading="loadingOrder" :order="order" :getMeta="getMeta" />
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
import KountPanel from "@/components/order-view-panels/KountPanel.vue";

const props = defineProps({
    id: String,
});

const orderId = computed(() => props.id);

const { order, loadingOrder, fetchOrder, createReplacement, resendEmail, getMeta, trackingNumber } = useOrder(orderId);

const customerId = computed(() => order.value?.customer_id || null);

const isArchived = computed(() => {
    return route.query.is_archived === "1" || route.query.is_archived === "true";
});

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
