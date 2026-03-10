<template>
    <div class="panel">
        <h3>Warehouse Export Report</h3>
        <p>Generate a CSV export of orders filtered by date range and warehouse.</p>

        <n-space vertical size="large">
            <div>
                <label>Date Range:</label>
                <n-date-picker v-model:value="dateRange" type="daterange" format="yyyy-MM-dd" style="width: 100%" />
            </div>

            <div>
                <label>Warehouse:</label>
                <n-select
                    v-model:value="selectedWarehouse"
                    :options="warehouseOptions"
                    placeholder="Select warehouse"
                    style="width: 100%"
                />
            </div>

            <n-progress
                v-if="progressPercent > 0"
                type="line"
                :percentage="progressPercent"
                :status="progressPercent === 100 ? 'success' : 'info'"
                indicator-placement="inside"
                processing
            />

            <n-button type="primary" :loading="loading" @click="startReport">Generate CSV Report</n-button>
        </n-space>

        <n-modal v-model:show="showModal" preset="dialog" title="Warehouse Export Report Ready">
            <template #default> Your warehouse export report is ready to download. </template>
            <template #action>
                <a
                    :href="downloadUrl"
                    :download="downloadUrl.split('/').pop()"
                    target="_blank"
                    @click="showModal = false"
                >
                    <n-button type="primary">Download CSV</n-button>
                </a>
            </template>
        </n-modal>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useReportChunks } from "@/composables/useReportChunks";

const emit = defineEmits(["report-created"]);

const dateRange = ref(null);
const selectedWarehouse = ref(null);

const warehouseOptions = [
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
];

function formatDate(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

const {
    loading,
    progress: progressPercent,
    showModal,
    downloadUrl,
    runReport,
} = useReportChunks("warehouse-export", 300);

async function startReport() {
    const hasRange = Array.isArray(dateRange.value) && dateRange.value.length === 2;

    if (!hasRange) {
        window.$message?.error("Please select a date range.");
        return;
    }

    if (!selectedWarehouse.value) {
        window.$message?.error("Please select a warehouse.");
        return;
    }

    const payload = {
        date_from: formatDate(dateRange.value[0]),
        date_to: formatDate(dateRange.value[1]),
        warehouse: selectedWarehouse.value,
        extension: "csv",
    };

    await runReport(payload);

    if (downloadUrl.value) {
        emit("report-created");
    }
}
</script>
