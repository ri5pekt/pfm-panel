<!-- ReportListPanel.vue -->
<template>
    <div class="panel">
        <h3>Previous Exports</h3>
        <n-skeleton v-if="loading" text :repeat="3" />

        <div v-else-if="reports.length" class="report-list">
            <div v-for="(r, i) in reports" :key="r.filename + i" class="report-item">
                <div class="left">
                    📄
                    <a :href="r.download_url" target="_blank" class="filename">
                        {{ r.filename }}
                    </a>
                </div>
                <div class="right">
                    <small>{{ formatDate(r.created_at) }}</small>
                </div>
            </div>
        </div>

        <p v-else>No previous reports found.</p>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, defineExpose } from "vue";
import { request } from "@/utils/api";

const props = defineProps({
    reportKey: String,
});

const reports = ref([]);
const loading = ref(true);
defineExpose({ loadReports });
async function loadReports() {
    loading.value = true;
    try {
        reports.value = await request({
            url: `/reports/history?report_type=${props.reportKey}`,
            useCustomApi: true,
        });
    } catch (err) {
        console.error("❌ Failed to load reports:", err);
    } finally {
        loading.value = false;
    }
}

function formatDate(dateString) {
    const d = new Date(dateString);
    return d.toLocaleString();
}

watch(() => props.reportKey, loadReports, { immediate: true });
</script>

<style scoped>
.report-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.report-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px dashed #ccc;
}

.left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.filename {
    font-weight: 400;
    color: var(--n-text-color);
    text-decoration: none;
}

.filename:hover {
    text-decoration: underline;
}

.right {
    font-size: 12px;
    color: #888;
}
</style>
