<!-- RefundsPanel.vue -->
<template>
    <div class="panel">
        <h3>Refunds Report</h3>
        <p>Select a date range to generate a CSV of processed refunds.</p>

        <n-space vertical size="large">
            <div>
                <label>Date Range:</label>
                <n-date-picker v-model:value="dateRange" type="daterange" format="yyyy-MM-dd" style="width: 100%" />
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

        <n-modal v-model:show="showModal" preset="dialog" title="Refund Report Ready">
            <template #default> Your refunds report is ready to download. </template>
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

function formatDate(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

const { loading, progress: progressPercent, showModal, downloadUrl, runReport } = useReportChunks("refunds", 300);

async function startReport() {
    if (!Array.isArray(dateRange.value) || dateRange.value.length !== 2) {
        return window.$message.error("Please select a valid date range.");
    }

    await runReport({
        date_from: formatDate(dateRange.value[0]),
        date_to: formatDate(dateRange.value[1]),
        extension: "csv",
    });

    if (downloadUrl.value) {
        emit("report-created");
    }
}
</script>
