<template>
    <n-space class="filters" align="end" wrap>
        <div class="filter-field">
            <n-text depth="3">Order Status</n-text>
            <n-select v-model:value="filters.status" :options="statusOptions" placeholder="All" clearable />
        </div>

        <div class="filter-field">
            <n-text depth="3">Tags</n-text>
            <n-select v-model:value="filters.tag" :options="tagFilterOptions" placeholder="All" clearable />
        </div>

        <div class="filter-field">
            <n-text depth="3">Warehouse</n-text>
            <n-select v-model:value="filters.warehouse" :options="warehouseOptions" placeholder="All" clearable />
        </div>

        <div class="filter-field">
            <n-text depth="3">Export Status</n-text>
            <n-select
                v-model:value="filters.export_status"
                :options="exportStatusOptions"
                placeholder="All"
                clearable
            />
        </div>

        <div class="filter-field">
            <n-text depth="3">Addr. Validation</n-text>
            <n-select v-model:value="filters.addr_status" :options="addrStatusOptions" placeholder="All" clearable />
        </div>

        <div class="filter-field">
            <DateRangeFilter
                :initial-from="route.query.date_from"
                :initial-to="route.query.date_to"
                @update:dateRange="handleDateRange"
            />
        </div>

        <n-button tertiary size="small" type="warning" @click="resetFilters"> Reset All </n-button>
    </n-space>
</template>

<script setup>
import { reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import DateRangeFilter from "@/components/ui-elements/DateRangeFilter.vue";

// Props & Emits
const props = defineProps({
    modelValue: Object,
});
const emit = defineEmits(["update:modelValue"]);

// Clone incoming props
const filters = props.modelValue;

const route = useRoute();
const router = useRouter();
function handleDateRange(range) {
    filters.date_from = range?.from || null;
    filters.date_to = range?.to || null;
}

/* Dropdown options */
const statusOptions = [
    { label: "All", value: null },
    { label: "Pending", value: "pending" },
    { label: "Processing", value: "processing" },
    { label: "On Hold", value: "on-hold" },
    { label: "Completed", value: "completed" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Refunded", value: "refunded" },
    { label: "Failed", value: "failed" },
];

const warehouseOptions = [
    { label: "All", value: null },
    { label: "Shipstation", value: "shipstation" },
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
];

const exportStatusOptions = [
    { label: "All", value: null },
    { label: "Pending", value: "pending" },
    { label: "Failed", value: "failed" },
    { label: "Exported", value: "exported" },
    { label: "Shipped", value: "shipped" },
];

const addrStatusOptions = [
    { label: "All", value: null },
    { label: "Pending", value: "pending-validation" },
    { label: "Invalid", value: "invalid" },
    { label: "Valid", value: "valid" },
    { label: "Error", value: "unavailable" },
    { label: "Not Validated", value: "not-validated" },
];

const tagFilterOptions = [
    { label: "All", value: null },
    { label: "PPU on-hold", value: "ppu-on-hold" },
    { label: "PPU Added", value: "ppu-added" },
    { label: "Facebook", value: "facebook" },
    { label: "Walmart", value: "walmart" },
    { label: "Subscription Renewal", value: "subscription-renewal" },
    { label: "Subscription Parent", value: "subscription-parent" },
    { label: "BAS Added", value: "bas-added" },
];

function resetFilters() {
    filters.status = null;
    filters.tag = null;
    filters.warehouse = null;
    filters.export_status = null;
    filters.addr_status = null;
    filters.date_from = null;
    filters.date_to = null;
}
</script>
