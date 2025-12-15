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
            <div class="row" style="display: flex; gap: 8px; align-items: flex-end">
                <!-- Search type -->
                <n-select
                    v-model:value="filters.search_type"
                    :options="searchTypeOptions"
                    placeholder="Search by"
                    clearable
                    style="width: 160px"
                />

                <!-- VALUE: text input for order_id / customer_email -->
                <template v-if="filters.search_type && filters.search_type !== 'products'">
                    <n-input
                        v-model:value="searchInput"
                        placeholder="Enter value"
                        style="width: 220px"
                        @keyup.enter="emitSearch"
                    />
                </template>

                <!-- VALUE: product multi-select (lazy loaded) -->
                <template v-else-if="filters.search_type === 'products'">
                    <n-select
                        v-model:value="selectedProductIds"
                        :options="productOptions"
                        :loading="productLoading"
                        multiple
                        tag
                        filterable
                        clearable
                        placeholder="Select products…"
                        style="min-width: 340px"
                        :render-label="(opt) => (opt.renderLabel ? opt.renderLabel() : opt.label)"
                        @focus="ensureProductsLoaded"
                        @update:show="
                            (open) => {
                                if (open) ensureProductsLoaded();
                            }
                        "
                    />
                </template>
                <!-- Search button -->
                <n-button
                    style="height: 34px"
                    v-if="filters.search_type"
                    size="small"
                    type="primary"
                    @click="emitSearch"
                >
                    Search
                </n-button>
            </div>
        </div>

        <n-button tertiary size="medium" type="default" @click="resetFilters"> Reset All </n-button>
    </n-space>
</template>

<script setup>
import { reactive, ref, watch, h } from "vue";
import { useRoute, useRouter } from "vue-router";
import DateRangeFilter from "@/components/ui-elements/DateRangeFilter.vue";
import { request } from "@/utils/api"; // <-- used for lazy product load

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

// Local state for search input (text modes) and product selection (products mode)
const searchInput = ref(filters.search_value ?? "");
const selectedProductIds = ref(Array.isArray(filters.search_value) ? filters.search_value.map(Number) : []); // for products mode

const { showStatus, showTags, showWarehouse, showExportStatus, showAddrStatus, showDate, showSearch } = props;

const route = useRoute();
const router = useRouter();
function handleDateRange(range) {
    filters.date_from = range?.from || null;
    filters.date_to = range?.to || null;
    emit("update:modelValue", { ...filters });
}

// Emit unified search (shape depends on search_type)
function emitSearch() {
    if (filters.search_type === "products") {
        // send Product ID array (numbers)
        filters.search_value = selectedProductIds.value.map((v) => Number(v));
    } else {
        // send trimmed string
        filters.search_value = (searchInput.value ?? "").trim();
    }
    emit("update:modelValue", { ...filters });
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
    { label: "Exception", value: "shipment_exception" },
    { label: "Delivered", value: "delivered" },
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
    { label: "Hotjar", value: "hotjar" },
    { label: "CS Added", value: "cs-added" },
];

// ✨ Add "Products" type
const searchTypeOptions = [
    { label: "Search by", value: null },
    { label: "Order ID", value: "order_id" },
    { label: "Customer Email", value: "customer_email" },
    { label: "Products", value: "products" }, // NEW
];

// ── Lazy product options for "Products" search ──
const productOptions = ref([]);
const productLoading = ref(false);
let productsLoaded = false;

async function ensureProductsLoaded() {
    if (productsLoaded || filters.search_type !== "products") return;
    productLoading.value = true;
    try {
        const res = await request({ url: "/products-by-category" });
        productOptions.value = (res || []).map((group) => ({
            type: "group",
            label: group.label,
            key: group.key,
            children: (group.products || []).map((p) => ({
                label: p.name,
                value: p.id, // we search by SKU
                sku: p.sku,
                id: p.id,
                price: p.price,
                image: p.image,
                renderLabel: () =>
                    h("div", { style: "display:flex;align-items:center;gap:8px" }, [
                        p.image
                            ? h("img", {
                                  src: p.image,
                                  style: "width:18px;height:18px;object-fit:cover;border-radius:2px",
                              })
                            : null,
                        h("span", `${p.name}`),
                    ]),
            })),
        }));
        productsLoaded = true;
    } catch (e) {
        console.error("Failed to load products", e);
    } finally {
        productLoading.value = false;
    }
}

// Reset local inputs when search_type changes
watch(
    () => filters.search_type,
    (newVal, oldVal) => {
        if (newVal === null) {
            // cleared
            searchInput.value = "";
            selectedProductIds.value = [];
            filters.search_value = null;
            emit("update:modelValue", { ...filters });
            emit("search", { ...filters });
            return;
        }

        if (newVal === "products") {
            // re-hydrate ID array
            selectedProductIds.value = Array.isArray(filters.search_value) ? filters.search_value.map(Number) : [];
        } else {
            // switching to text-based mode
            searchInput.value = typeof filters.search_value === "string" ? filters.search_value : "";
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
    searchInput.value = "";
    selectedProductIds.value = [];
    emit("update:modelValue", { ...filters });
    emit("search", { ...filters });
}
</script>
