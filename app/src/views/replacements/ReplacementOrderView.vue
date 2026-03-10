<!-- ReplacementOrderView.vue -->
<template>
    <div class="order-view">
        <div class="page-top">
            <n-button @click="backToReplacements">← Back to Replacement Orders</n-button>
            <div class="page-title">
                Order #{{ props.id }}
                <n-tag type="info" size="small" style="margin-left: 8px; vertical-align: middle"> Replacement </n-tag>
            </div>
        </div>
        <div class="order-grid">
            <!-- Order Info Panel -->
            <OrderInfoPanel
                :order="order"
                :loading="loadingOrder"
                @update-order="handleOrderUpdate"
                :getMeta="getMeta"
                :isArchived="false"
                sourceType="replacement"
            />

            <OrderNotesPanel :orderId="props.id" :refreshKey="notesRefreshKey" sourceType="replacement" />

            <!-- Order Totals Panel -->
            <OrderTotalsPanel
                :order="order"
                :orderId="props.id"
                :loading="loadingOrder"
                @updateOrder="handleOrderUpdate"
                sourceType="replacement"
            />

            <!-- Warehouse Export Panel -->
            <WarehouseExportPanel
                :loading="loadingOrder"
                :getMeta="getMeta"
                :trackingNumber="trackingNumber"
                :orderId="props.id"
                @updateOrder="handleOrderUpdate"
                sourceType="replacement"
            />
        </div>
    </div>
</template>

<script setup>
import { useOrder } from "@/composables/useOrder";
import { useRouter } from "vue-router";
import { NButton, NTag } from "naive-ui";
import { computed, ref } from "vue";
import OrderInfoPanel from "@/components/order-view-panels/OrderInfoPanel.vue";
import OrderNotesPanel from "@/components/order-view-panels/OrderNotesPanel.vue";
import OrderTotalsPanel from "@/components/order-view-panels/OrderTotalsPanel.vue";
import WarehouseExportPanel from "@/components/order-view-panels/WarehouseExportPanel.vue";
import { useReplacementWorkTabs } from "@/composables/useReplacementWorkTabs";

const router = useRouter();
const { closeTab, keyForReplacementId, mainKey, setActiveKey } = useReplacementWorkTabs();

const props = defineProps({
    id: String,
});

const notesRefreshKey = ref(0);
function refreshNotes() {
    notesRefreshKey.value = Date.now();
}

const orderId = computed(() => props.id);

const { order, loadingOrder, fetchOrder, getMeta, trackingNumber } = useOrder(orderId, "replacement");

function handleOrderUpdate() {
    fetchOrder();
    refreshNotes();
}

function backToReplacements() {
    if (props.id) closeTab(keyForReplacementId(props.id));
    setActiveKey(mainKey());
    router.push({ name: "replacements" });
}
</script>
