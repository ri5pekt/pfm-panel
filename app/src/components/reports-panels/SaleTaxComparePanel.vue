<!-- SaleTaxComparePanel.vue -->

<template>
    <div class="panel">
        <h3>Sale Tax Compare (file upload)</h3>
        <p>Upload a CSV file to compare WooCommerce and Braintree tax calculations. The file will be processed in chunks and a new CSV with added columns will be generated.</p>

        <n-space vertical size="large">
            <div>
                <label>Upload CSV File:</label>
                <n-upload
                    v-model:file-list="fileList"
                    :max="1"
                    accept=".csv"
                    :show-file-list="true"
                    @change="handleFileChange"
                >
                    <n-button>Select CSV File</n-button>
                </n-upload>
            </div>

            <n-progress
                v-if="progressPercent > 0"
                type="line"
                :percentage="progressPercent"
                :status="progressPercent === 100 ? 'success' : 'info'"
                indicator-placement="inside"
                processing
            />

            <n-button type="primary" :loading="loading" :disabled="!selectedFile" @click="startReport">
                Generate CSV Report
            </n-button>
        </n-space>

        <n-modal v-model:show="showModal" preset="dialog" title="Report Ready">
            <template #default> Your Sale Tax Compare Report is ready for download. </template>
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
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";

const props = defineProps({ reportKey: String });
const emit = defineEmits(["report-created"]);

const fileList = ref([]);
const selectedFile = ref(null);
const loading = ref(false);
const progressPercent = ref(0);
const showModal = ref(false);
const downloadUrl = ref(null);
const message = useMessage();

function handleFileChange(options) {
    if (options.fileList.length > 0) {
        selectedFile.value = options.fileList[0].file;
    } else {
        selectedFile.value = null;
    }
}

async function startReport() {
    if (!selectedFile.value) {
        return message.error("Please select a CSV file.");
    }

    loading.value = true;
    progressPercent.value = 0;
    showModal.value = false;
    downloadUrl.value = null;

    try {
        // Read file content
        const fileContent = await readFileAsText(selectedFile.value);

        // Process in chunks
        const chunkSize = 30;
        let offset = 0;
        let allData = [];
        let remaining = 1; // Start with 1 to enter the loop

        while (remaining > 0) {
            const payload = {
                report_type: "sale-tax-compare",
                offset: offset,
                chunk_size: chunkSize,
                file_content: fileContent,
                extension: "csv",
            };

            const response = await request({
                method: "POST",
                url: "/reports/run",
                body: payload,
            });

            if (response.data && Array.isArray(response.data)) {
                allData = allData.concat(response.data);
            }

            remaining = response.remaining ?? 0;
            offset += chunkSize;

            // Update progress
            const totalRows = allData.length + remaining;
            progressPercent.value = totalRows > 0
                ? Math.round((allData.length / totalRows) * 100)
                : 100;
        }

        // Upload final report
        const content = allData.join("\n");

        const uploadPayload = {
            report_type: "sale-tax-compare",
            file_content: content,
            extension: "csv",
        };

        const uploadResponse = await request({
            method: "POST",
            url: "/reports/upload",
            useCustomApi: true,
            body: uploadPayload,
        });

        progressPercent.value = 100;
        message.success("📁 Report created!");

        if (uploadResponse.download_url) {
            downloadUrl.value = uploadResponse.download_url;
            showModal.value = true;
            emit("report-created");
        }
    } catch (err) {
        console.error("Report generation failed:", err);
        message.error("Report generation failed: " + (err.message || "Unknown error"));
    } finally {
        loading.value = false;
    }
}

function readFileAsText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = (e) => reject(e);
        reader.readAsText(file);
    });
}
</script>

