// composables/useReportChunks.js
import { ref } from "vue";
import { request } from "@/utils/api";
import { useMessage } from "naive-ui";

export function useReportChunks(reportType, chunkSize = 300) {
    const loading = ref(false);
    const progress = ref(0);
    const error = ref(null);
    const downloadUrl = ref(null);
    const showModal = ref(false);
    const message = useMessage();

    async function runReport(params = {}, onChunk = null) {
        loading.value = true;
        progress.value = 0;
        error.value = null;
        showModal.value = false;
        downloadUrl.value = null;

        const payload = {
            report_type: reportType,
            offset: 0,
            chunk_size: chunkSize,
            ...params,
        };

        try {
            console.log("🚀 Sending initial payload:", payload);

            const initial = await request({
                method: "POST",
                url: "/reports/run",
                body: payload,
            });

            console.log("📥 Initial response received:", initial);

            let allData = [...initial.data];
            let remaining = initial.remaining;
            let processed = allData.length;

            // 💡 Update progress right after initial response
            progress.value = Math.round((processed / (processed + remaining)) * 100);

            if (onChunk) onChunk(initial.data);

            while (remaining > 0) {
                payload.offset += chunkSize;

                console.log("➡️ Sending chunk payload:", payload);

                const next = await request({
                    method: "POST",
                    url: "/reports/run",
                    body: payload,
                });

                console.log("📥 Chunk response received:", next);

                if (onChunk) onChunk(next.data);

                allData = allData.concat(next.data);
                processed += next.data.length;
                remaining = next.remaining;

                progress.value = Math.round((processed / (processed + remaining)) * 100);
            }

            const content = allData.join("\n");

            const uploadPayload = {
                report_type: reportType,
                file_content: content,
                extension: "txt",
                ...params,
            };

            console.log("📤 Uploading report with payload:", uploadPayload);

            const uploadResponse = await request({
                method: "POST",
                url: "/reports/upload",
                useCustomApi: true,
                body: uploadPayload,
            });

            console.log("✅ Upload response:", uploadResponse);

            progress.value = 100;
            message.success("📁 Report created!");

            if (uploadResponse.download_url) {
                downloadUrl.value = uploadResponse.download_url;
                showModal.value = true;
            }
        } catch (err) {
            console.error("❌ Report generation failed:", err);
            error.value = err;
            message.error("Report generation failed.");
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        progress,
        error,
        showModal,
        downloadUrl,
        runReport,
    };
}
