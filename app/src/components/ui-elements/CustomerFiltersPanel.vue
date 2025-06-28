<!-- CustomerFiltersPanel.vue -->
<template>
    <n-space class="filters" align="end" wrap>
        <div class="filter-field">
            <n-text depth="3">Search Customer</n-text>
            <div class="row">
                <n-select
                    v-model:value="internalFilters.search_type"
                    :options="searchTypeOptions"
                    placeholder="Search by"
                    clearable
                    style="width: 150px"
                    @update:value="onSearchTypeChange"
                />
                <div v-if="showSearchValue" style="display: flex; gap: 6px">
                    <n-input
                        v-model:value="internalFilters.search_value"
                        placeholder="Enter value"
                        style="width: 150px"
                        @keyup.enter="emitSearch"
                        @input="onSearchValueInput"
                    />
                    <n-button size="small" type="primary" @click="emitSearch"> Search </n-button>
                </div>
            </div>
        </div>
        <div class="filter-field">
            <DateRangeFilter
                titleLabel="Registered"
                :initial-from="internalFilters.registered_from"
                :initial-to="internalFilters.registered_to"
                @update:dateRange="onRegisteredDate"
            />
        </div>
        <n-button tertiary size="medium" type="default" @click="resetFilters"> Reset All </n-button>
    </n-space>
</template>

<script setup>
import { reactive, computed, watch } from "vue";
import DateRangeFilter from "@/components/ui-elements/DateRangeFilter.vue";

const props = defineProps({
    modelValue: Object,
});
const emit = defineEmits(["update:modelValue", "search"]);

const internalFilters = reactive({
    search_type: null,
    search_value: null,
    registered_from: null,
    registered_to: null,
});

// sync with parent
watch(
    () => props.modelValue,
    (val) => {
        Object.assign(internalFilters, val || {});
    },
    { immediate: true, deep: true }
);

const searchTypeOptions = [
    { label: "Search by", value: null },
    { label: "Customer Name", value: "customer_name" },
    { label: "Customer Email", value: "customer_email" },
    { label: "Order ID", value: "order_id" },
];

const showSearchValue = computed(() => !!internalFilters.search_type && internalFilters.search_type !== null);

function onSearchTypeChange(val) {
    // Reset all other filters when dropdown changes
    internalFilters.registered_from = null;
    internalFilters.registered_to = null;
    if (!val) internalFilters.search_value = null;
    emit("update:modelValue", { ...internalFilters });
    // DO NOT emit search yet!
}

function onSearchValueInput() {
    // Don't run search automatically
}

function emitSearch() {
    // Only run search if there is a value AND a type
    if (internalFilters.search_type && internalFilters.search_value) {
        emit("update:modelValue", { ...internalFilters });
        emit("search", { ...internalFilters });
    }
}

// Dates: run search immediately
function onRegisteredDate(range) {
    internalFilters.search_type = null;
    internalFilters.search_value = null;
    internalFilters.registered_from = range?.from || null;
    internalFilters.registered_to = range?.to || null;
    emit("update:modelValue", { ...internalFilters });
    emit("search", { ...internalFilters });
}
function onLastOrderDate(range) {
    internalFilters.search_type = null;
    internalFilters.search_value = null;
    internalFilters.registered_from = null;
    internalFilters.registered_to = null;
    emit("update:modelValue", { ...internalFilters });
    emit("search", { ...internalFilters });
}
function resetFilters() {
    internalFilters.search_type = null;
    internalFilters.search_value = null;
    internalFilters.registered_from = null;
    internalFilters.registered_to = null;
    emit("update:modelValue", { ...internalFilters });
    emit("search", { ...internalFilters });
}
</script>
