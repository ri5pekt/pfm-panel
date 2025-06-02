<template>
    <div class="panel warehouse-export-panel">
        <h3>Warehouse Export</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <p><strong>Warehouse:</strong> {{ getMeta("warehouse_to_export") || "—" }}</p>
            <p><strong>Export Status:</strong> {{ getMeta("warehouse_export_status") || "—" }}</p>
            <p><strong>Address Validated:</strong> {{ getMeta("validate_address_status") || "—" }}</p>
            <p v-if="trackingNumber"><strong>Tracking Number:</strong> {{ trackingNumber }}</p>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    loading: Boolean,
    getMeta: Function,
    trackingNumber: String,
});
</script>
