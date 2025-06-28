
<!-- OrderFiltersPanel.vue -->
<template>
    <n-space class="filters" align="end" wrap>
        <div v-if="showStatus" class="filter-field">
            <n-text depth="3">Order Status</n-text>
            <n-select v-model:value="filters.status" :options="statusOptions" placeholder="All" clearable />
        </div>

        <div v-if="showTags" class="filter-field">
            <n-text depth="3">Tags</n-text>
            <n-select v-model:value="filters.tag" :options="tagFilterOptions" placeholder="All" clearable />
        </div>

        <div v-if="showWarehouse" class="filter-field">
            <n-text depth="3">Warehouse</n-text>
            <n-select v-model:value="filters.warehouse" :options="warehouseOptions" placeholder="All" clearable />
        </div>

        <div v-if="showExportStatus" class="filter-field">
            <n-text depth="3">Export Status</n-text>
            <n-select
                v-model:value="filters.export_status"
                :options="exportStatusOptions"
                placeholder="All"
                clearable
            />
        </div>

        <div v-if="showAddrStatus" class="filter-field">
            <n-text depth="3">Addr. Validation</n-text>
            <n-select v-model:value="filters.addr_status" :options="addrStatusOptions" placeholder="All" clearable />
        </div>

        <div v-if="showDate" class="filter-field">
            <DateRangeFilter
                :initial-from="filters.date_from"
                :initial-to="filters.date_to"
                @update:dateRange="handleDateRange"
            />
        </div>

        <div v-if="showSearch" class="filter-field">
            <n-text depth="3">Search Orders</n-text>
            <div class="row">
                <n-select
                    v-model:value="filters.search_type"
                    :options="searchTypeOptions"
                    placeholder="Search by"
                    clearable
                    style="width: 130px"
                />
                <div v-if="filters.search_type" style="display: flex; gap: 6px;">
                    <n-input
                        v-model:value="filters.search_value"
                        placeholder="Enter value"
                        style="width: 150px"
                        @keyup.enter="emitSearch"
                    />
                    <n-button size="small" type="primary" @click="emitSearch"> Search </n-button>
                </div>
            </div>
        </div>

        <n-button tertiary size="medium" type="default" @click="resetFilters"> Reset All </n-button>
    </n-space>
</template>

<script setup>
import { reactive, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import DateRangeFilter from "@/components/ui-elements/DateRangeFilter.vue";

// Props & Emits
const props = defineProps({
    modelValue: Object,
    showStatus: { type: Boolean, default: true },
    showTags: { type: Boolean, default: true },
    showWarehouse: { type: Boolean, default: true },
    showExportStatus: { type: Boolean, default: true },
    showAddrStatus: { type: Boolean, default: true },
    showDate: { type: Boolean, default: true },
    showSearch: { type: Boolean, default: true },
});
const emit = defineEmits(["update:modelValue", "search"]);

// Clone incoming props
const filters = props.modelValue;

const { showStatus, showTags, showWarehouse, showExportStatus, showAddrStatus, showDate, showSearch } = props;


const route = useRoute();
const router = useRouter();
function handleDateRange(range) {
    console.log("Date range selected:", range);
    filters.date_from = range?.from || null;
    filters.date_to = range?.to || null;
    emit("update:modelValue", { ...filters });
}

function emitSearch() {
    emit("search", { ...filters });
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

const searchTypeOptions = [
    { label: "Search by", value: null },
    { label: "Order ID", value: "order_id" },
    { label: "Customer Email", value: "customer_email" },
];

watch(
    () => filters.search_type,
    (newVal, oldVal) => {
        if (newVal === null && oldVal !== null) {
            filters.search_value = null;
            emit("update:modelValue", { ...filters });
            emit("search", { ...filters });
        }
    }
);

function resetFilters() {
    filters.status = null;
    filters.tag = null;
    filters.warehouse = null;
    filters.export_status = null;
    filters.addr_status = null;
    filters.date_from = null;
    filters.date_to = null;
    filters.search_type = null;
    filters.search_value = null;
    emit("update:modelValue", { ...filters });
    emit("search", { ...filters });
}
</script>
