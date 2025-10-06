<!-- WarehouseExportPanel.vue -->
<template>
    <div class="panel warehouse-export-panel">
        <h3>Warehouse Export</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <div class="address-columns">
                <div class="address-section">
                    <p>
                        <strong>Warehouse:</strong>
                        <span
                            class="warehouse-export-tag"
                            :class="[
                                `warehouse-${getMeta('warehouse_to_export') || 'none'}`,
                                getMeta('warehouse_export_status')
                                    ? `warehouse-export-${getMeta('warehouse_export_status')}`
                                    : 'warehouse-export-none',
                            ]"
                        >
                            {{ getMeta("warehouse_to_export") || "—" }}
                        </span>
                    </p>

                    <p>
                        <strong>Export Status:</strong>
                        <span
                            class="warehouse-export-status-tag"
                            :class="`warehouse-export-status-${getMeta('warehouse_export_status') || 'none'}`"
                        >
                            {{ exportStatusLabel }}
                        </span>
                    </p>

                    <p>
                        <strong>Address Validated:</strong>
                        <span
                            :class="[
                                'address-status-pill',
                                'address-validation-status-' + (getMeta('validate_address_status') || 'not-validated'),
                            ]"
                        >
                            {{ getMeta("validate_address_status") || "—" }}
                        </span>
                    </p>
                    <p v-if="trackingNumber">
                        <strong>Tracking Number: </strong>
                        <a :href="`https://particle.aftership.com/${trackingNumber}`" target="_blank">
                            {{ trackingNumber }}
                        </a>
                    </p>

                    <div v-if="$can('send_to_warehouse')" class="address-actions">
                        <n-space horisontal size="small">
                            <n-button
                                size="medium"
                                type="default"
                                :loading="validating"
                                :disabled="getMeta('validate_address_status') === 'valid'"
                                @click="revalidateAddress"
                            >
                                Revalidate Address
                            </n-button>

                            <n-button
                                :loading="forceValidating"
                                :disabled="getMeta('validate_address_status') === 'valid'"
                                @click="forceValidateAddress"
                                size="medium"
                                type="default"
                            >
                                Force Validate Address
                            </n-button>
                        </n-space>
                        <div v-if="$can('edit_orders_info')" class="warehouse-actions" style="margin-top: 1.5rem">
                            <n-space horisontal size="small">
                                <n-button
                                    size="medium"
                                    type="default"
                                    :loading="exporting"
                                    :disabled="!selectedWarehouse"
                                    @click="exportToWarehouse"
                                >
                                    Export to Warehouse
                                </n-button>
                                <n-select
                                    v-model:value="selectedWarehouse"
                                    :options="warehouseOptions"
                                    placeholder="Select warehouse"
                                    style="width: 200px"
                                />
                            </n-space>
                        </div>
                    </div>
                </div>
                <div
                    class="address-section"
                    v-if="props.getMeta('warehouse_to_export') === 'shipbob' && props.getMeta('warehouse_shipment_id')"
                >
                    <h4>Detailed Timeline</h4>
                    <n-skeleton v-if="loadingTimeline" text :repeat="4" />
                    <ul v-else class="timeline-list">
                        <li v-for="event in timelineEvents" :key="event.timestamp" class="timeline-entry">
                            <small>{{ new Date(event.timestamp).toLocaleString() }}</small>
                            <strong>{{ event.log_type_text }}</strong>
                        </li>
                        <li v-if="timelineEvents.length === 0">No timeline available.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useDialog, useMessage } from "naive-ui";
import { request } from "@/utils/api";
import { ref, computed, watchEffect } from "vue";

const dialog = useDialog();
const message = useMessage();
const validating = ref(false);
const forceValidating = ref(false);

const selectedWarehouse = ref(null);
const exporting = ref(false);

const warehouseOptions = [
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
    {
        label: "Decide based on rules",
        value: "decide_based_on_rules",
    },
];

const props = defineProps({
    loading: Boolean,
    getMeta: Function,
    trackingNumber: String,
    orderId: [String, Number],
    sourceType: {
        type: String,
        default: "order", // or 'replacement'
    },
});

const baseUrl = computed(() =>
    props.sourceType === "replacement" ? `/replacements/${props.orderId}` : `/orders/${props.orderId}`
);

watchEffect(() => {
    const meta = props.getMeta("warehouse_to_export");
    if (meta && meta !== "null" && meta !== "undefined") {
        selectedWarehouse.value = meta;
    }
});

const exportStatusLabel = computed(() => {
    const status = props.getMeta?.("warehouse_export_status");
    if (!status) return "—";

    return (
        {
            pending: "Pending",
            failed: "Failed",
            exported: "Exported",
            shipped: "Shipped",
        }[status] || status
    );
});

const emit = defineEmits(["updateOrder"]);

async function exportToWarehouse() {
    if (!selectedWarehouse.value) return;

    exporting.value = true;

    try {
        const json = await request({
            url: `${baseUrl.value}/export-to-warehouse`,
            method: "POST",
            body: { warehouse: selectedWarehouse.value },
        });

        const dialogType = json.success ? dialog.success : dialog.error;
        dialogType({
            title: json.success ? "Export Success" : "Export Failed",
            content: json.message,
            positiveText: "OK",
        });

        if (json.success) emit("updateOrder");
    } catch (e) {
        const status = e?.status ? ` (HTTP ${e.status})` : "";
        const msg =
            (e?.body && (e.body.message || e.body.error || e.body.detail)) ||
            e?.message ||
            "Could not export to warehouse.";

        dialog.error({
            title: `Export Failed${status}`,
            content: msg,
            positiveText: "OK",
        });

        // Keep rich details in console for debugging
        console.error("❌ Export failed", {
            status: e?.status,
            message: msg,
            body: e?.body,
            raw: e?.bodyText,
            url: e?.url,
        });
    } finally {
        exporting.value = false;
    }
}

async function revalidateAddress() {
    validating.value = true;
    try {
        const result = await request({
            url: `${baseUrl.value}/revalidate-address`,
            method: "POST",
            body: { force: false },
        });

        const dialogType = result.success ? dialog.success : dialog.error;

        dialogType({
            title: result.success ? "Success" : "Validation Failed",
            content: result.message,
            positiveText: "OK",
            onPositiveClick: () => {
                if (result.success) emit("updateOrder");
            },
        });
    } catch (e) {
        dialog.error({
            title: "Error",
            content: "Something went wrong. Try again later.",
            positiveText: "Got it",
        });
        console.error("❌ Address revalidation failed:", e);
    } finally {
        validating.value = false;
    }
}

const forceValidateAddress = async () => {
    try {
        forceValidating.value = true;

        const result = await request({
            url: `${baseUrl.value}/revalidate-address`,
            method: "POST",
            body: { force: true },
        });

        const dialogType = result.success ? dialog.success : dialog.error;

        dialogType({
            title: result.success ? "Address Forced" : "Failed",
            content: result.message || "Could not force validate address",
            positiveText: "Got it",
            onPositiveClick: () => {
                if (result.success) emit("updateOrder");
            },
        });
    } catch (err) {
        dialog.error({
            title: "Unexpected Error",
            content: "Could not reach the server.",
            positiveText: "Understood",
        });
        console.error(err);
    } finally {
        forceValidating.value = false;
    }
};

const timelineEvents = ref([]);
const loadingTimeline = ref(false);

async function loadShipBobTimeline() {
    loadingTimeline.value = true;

    const warehouseShipmentId = props.getMeta("warehouse_shipment_id") || "";
    console.log("Loading ShipBob timeline for ID:", warehouseShipmentId);
    try {
        console.log("Fetching ShipBob timeline...");
        const response = await fetch(`https://api.shipbob.com/2.0/shipment/${warehouseShipmentId}/timeline`, {
            headers: {
                Authorization: "Bearer 3E673925F80C19A56356A01DEA7E6CA85E4D5A6A50B0512AC92E2977AF37EBBD-1",
            },
        });

        if (!response.ok) throw new Error("API request failed");

        const data = await response.json();
        timelineEvents.value = data;
    } catch (err) {
        console.error("❌ Failed to load timeline:", err);
        timelineEvents.value = [];
    } finally {
        loadingTimeline.value = false;
    }
}

watchEffect(() => {
    const toExport = props.getMeta("warehouse_to_export");
    const shipmentId = props.getMeta("warehouse_shipment_id");
    const alreadyLoaded = timelineEvents.value.length > 0;
    const isBusy = loadingTimeline.value;

    const shouldLoad = toExport === "shipbob" && !!shipmentId && !alreadyLoaded && !isBusy;

    if (shouldLoad) {
        console.log("📦 Loading timeline now!");
        loadShipBobTimeline();
    }
});
</script>
