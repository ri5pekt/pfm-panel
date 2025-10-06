<template>
    <div class="panel">
        <h3>Export Orders to Narvar</h3>
        <p>Select a date range or enter order IDs to generate the export file.</p>

        <n-space vertical size="large">
            <div>
                <label>Date Range:</label>
                <n-date-picker v-model:value="dateRange" type="daterange" format="yyyy-MM-dd" style="width: 100%" />
            </div>

            <div>
                <label>Or enter Order IDs (comma or space separated):</label>
                <n-input v-model:value="orderIdsText" type="textarea" :autosize="{ minRows: 2, maxRows: 4 }" />
            </div>

            <n-progress
                v-if="progressPercent > 0"
                type="line"
                :percentage="progressPercent"
                :status="progressPercent === 100 ? 'success' : 'info'"
                indicator-placement="inside"
                processing
            />

            <n-button type="primary" :loading="loading" @click="startReport"> Generate Export </n-button>
        </n-space>

        <n-modal v-model:show="showModal" preset="dialog" title="Export Ready">
            <template #default> Your Narvar export file is ready to download. </template>
            <template #action>
                <a
                    :href="downloadUrl"
                    :download="downloadUrl.split('/').pop()"
                    target="_blank"
                    @click="showModal = false"
                >
                    <n-button type="primary">Download</n-button>
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
const orderIdsText = ref("");

function formatDate(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

// Server already supports type='export-to-narvar'
const {
    loading,
    progress: progressPercent,
    showModal,
    downloadUrl,
    runReport,
} = useReportChunks("export-to-narvar", 300);

async function startReport() {
    const hasRange = Array.isArray(dateRange.value) && dateRange.value.length === 2;
    const orderIds = orderIdsText.value
        .split(/[\s,]+/)
        .map((id) => parseInt(id))
        .filter((id) => !isNaN(id));

    if (!hasRange && orderIds.length === 0) {
        return window.$message.error("Please select a date range or enter at least one order ID.");
    }

    const date_from = hasRange ? formatDate(dateRange.value[0]) : null;
    const date_to = hasRange ? formatDate(dateRange.value[1]) : null;

    await runReport({
        date_from,
        date_to,
        order_ids: orderIds,
        // If your server returns CSV, keep csv. If it returns JSON/NDJSON, swap to "json".
        extension: "csv",
    });

    if (downloadUrl.value) emit("report-created");
}
</script>
