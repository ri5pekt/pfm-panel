<template>
    <div class="order-status-counts-panel">
        <n-card size="small" class="status-card">
            <n-spin :show="loading">
                <div class="status-list">
                    <button
                        v-for="status in orderedStatuses"
                        :key="status"
                        class="status-item"
                        :class="{
                            active: (status === 'all' && !route.query.status) || route.query.status === status,
                        }"
                        @click="applyStatus(status)"
                    >
                        {{ labels[status] || status }}
                        <span class="count">({{ (counts[status] || 0).toLocaleString() }})</span>
                    </button>
                </div>
            </n-spin>
        </n-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api"; // ⬅️ same helper you use in OrdersList.vue

const emit = defineEmits(["select-status"]);
const route = useRoute();
const router = useRouter();
const message = useMessage();

const loading = ref(false);
const counts = ref({});

// Woo-style labels
const labels = {
    all: "All",
    pending: "Pending payment",
    processing: "Processing",
    "on-hold": "On hold",
    completed: "Completed",
    cancelled: "Cancelled",
    refunded: "Refunded",
    failed: "Failed",
};

const orderedStatuses = computed(() => {
    const blacklist = ["checkout-draft"];
    const order = ["all", "on-hold", "failed", "refunded"];

    const keys = Object.keys(labels).filter((k) => !order.includes(k) && !blacklist.includes(k));

    return [...order, ...keys];
});

async function loadCounts() {
    loading.value = true;
    try {
        const res = await request({
            url: `/orders/status-counts`,
            raw: true,
        });
        counts.value = await res.json();
        // Fallback for "all"
        if (counts.value && counts.value.all == null) {
            counts.value.all = Object.entries(counts.value).reduce(
                (sum, [k, v]) => (k === "all" ? sum : sum + (Number(v) || 0)),
                0
            );
        }
        console.log("📊 Loaded status counts:", counts.value);
    } catch (err) {
        console.error(err);
        message.error("Failed to load status counts");
    } finally {
        loading.value = false;
    }
}

function applyStatus(status) {
    emit("select-status", status);
}

onMounted(loadCounts);
defineExpose({ reload: loadCounts });
</script>

<style scoped>
.status-card {
    margin-top: 12px;
}
.status-header {
    font-weight: 600;
    margin-bottom: 6px;
}
.status-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.status-item {
    appearance: none;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #fff;
    border-radius: 6px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    font-size: 12px;
    line-height: 1.2;
}
.status-item:hover {
    background: rgba(0, 0, 0, 0.04);
}
.status-item.active {
    background: #0077b6;
    color: #fff;
    border-color: #0077b6;
    font-weight: 600;
}
.status-item .count {
    opacity: 0.8;
    margin-left: 4px;
}
</style>
