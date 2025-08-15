<!-- OrdersToPriorityPanel.vue -->
<template>
    <div class="panel">
        <h3>New Report</h3>
        <p>Select a date range to export orders to or enter multiple Order IDs.</p>

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
            <n-button type="primary" :loading="loading" @click="startReport">Create Report</n-button>
        </n-space>

        <n-modal v-model:show="showModal" preset="dialog" title="Report Ready">
            <template #default> Your report is ready to download. </template>
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

const props = defineProps({ reportKey: String });
const emit = defineEmits(["report-created"]);

const dateRange = ref(null);
const orderIdsText = ref("");

function formatDateFromTimestamp(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

const {
    loading,
    progress: progressPercent,
    showModal,
    downloadUrl,
    runReport,
} = useReportChunks("orders-to-priority");

async function startReport() {
    const hasDateRange = Array.isArray(dateRange.value) && dateRange.value.length === 2;
    const orderIds = orderIdsText.value
        .split(/[\s,]+/)
        .map((id) => parseInt(id))
        .filter((id) => !isNaN(id));

    if (!hasDateRange && orderIds.length === 0) {
        return window.$message.error("Please select a date range or enter at least one order ID.");
    }

    const date_from = hasDateRange ? formatDateFromTimestamp(dateRange.value[0]) : null;
    const date_to = hasDateRange ? formatDateFromTimestamp(dateRange.value[1]) : null;

    await runReport({
        date_from,
        date_to,
        order_ids: orderIds,
    });

    if (downloadUrl.value) {
        emit("report-created");
    }
}
</script>
