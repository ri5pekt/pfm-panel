<!-- customer-view-panels/YotpoPanel.vue -->
<template>
    <div class="panel yotpo-panel">
        <h3>Yotpo {{ title }}</h3>
        <n-skeleton v-if="loading" text :repeat="6" />

        <div v-else-if="yotpo">
            <div class="row" style="display: flex; gap: 8px 24px; align-items: center; flex-wrap: wrap">
                <p><strong>Points:</strong> {{ yotpo.points_balance }}</p>
                <p><strong>Credit Balance:</strong> {{ yotpo.credit_balance || "—" }}</p>
                <p><strong>Total Purchases:</strong> {{ yotpo.total_purchases }}</p>

                <n-button
                    v-if="props.source !== 'sweepstakes' && $can('edit_loyalty')"
                    type="primary"
                    @click="showAdjustModal = true"
                    class="mt-4"
                >
                    Adjust Customer's Point Balance
                </n-button>
            </div>

            <n-modal
                v-model:show="showAdjustModal"
                preset="dialog"
                title="Adjust Customer's Point Balance"
                @positive-click="handleAdjustPoints"
                @negative-click="showAdjustModal = false"
                positive-text="Proceed"
                negative-text="Cancel"
                :positive-button-props="{ loading: adjustingPoints, disabled: adjustingPoints }"
            >
                <template #default>
                    <div style="display: flex; flex-direction: column; gap: 1rem">
                        <n-input-number
                            v-model:value="adjustPointsValue"
                            placeholder="Enter point adjustment (e.g., -100 or 200)"
                            clearable
                            :min="-100000"
                            :max="100000"
                        />
                        <n-input
                            v-model:value="adjustReason"
                            type="textarea"
                            placeholder="Optional reason for point adjustment"
                            clearable
                            rows="3"
                        />
                        <p style="font-size: 0.875rem; color: #888">
                            To reduce points, enter a negative number. Leave reason blank if not needed.
                        </p>
                    </div>
                </template>
            </n-modal>

            <div v-if="yotpo.history?.length" class="history-table-wrapper">
                <h4 style="margin-top: 20px">Points History</h4>
                <n-data-table
                    :columns="columns"
                    :data="reversedHistory"
                    :pagination="false"
                    size="small"
                    :max-height="200"
                    bordered
                />
            </div>
        </div>

        <div v-else>
            <n-alert type="warning">Yotpo data not found for this customer.</n-alert>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { request } from "@/utils/api";
import { useMessage } from "naive-ui";

const props = defineProps({
    customerId: Number,
    source: {
        type: String,
        default: "loyalty",
    },
});

const title = computed(() => {
    return props.source === "sweepstakes" ? "Sweepstakes" : "Loyalty";
});

const yotpo = ref(null);
const loading = ref(false);
const message = useMessage();

async function fetchYotpoData() {
    loading.value = true;
    try {
        const res = await request({
            url: `/customers/${props.customerId}/yotpo?source=${props.source}`,
        });
        yotpo.value = res;
    } catch (err) {
        console.error("Failed to load Yotpo data:", err);
    } finally {
        loading.value = false;
    }
}

watch(() => props.customerId, fetchYotpoData, { immediate: true });

function formatDate(dateStr) {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString("en-GB", {
        day: "numeric",
        month: "short",
    });
}

const reversedHistory = computed(() => {
    return yotpo.value?.history ? [...yotpo.value.history].reverse() : [];
});

const columns = [
    {
        title: "Date",
        key: "completed_at",
        render(row) {
            return formatDate(row.completed_at);
        },
    },
    {
        title: "Action",
        key: "action",
    },
    {
        title: "Points",
        key: "points",
        render(row) {
            return row.completed_at ? row.points : "—";
        },
    },
    {
        title: "Status",
        key: "status",
    },
];

const showAdjustModal = ref(false);
const adjustPointsValue = ref(null);
const adjustReason = ref("");
const adjustingPoints = ref(false);

async function handleAdjustPoints() {
    if (adjustPointsValue.value === null || adjustPointsValue.value === 0) {
        message.warning("Please enter a non-zero point adjustment.");
        return false;
    }

    adjustingPoints.value = true;

    try {
        await request({
            url: `/customers/${props.customerId}/yotpo-adjust?source=${props.source}`,
            method: "POST",
            body: {
                points: adjustPointsValue.value,
                reason: adjustReason.value,
            },
        });

        message.success("Point adjustment successful!");
        showAdjustModal.value = false;
        fetchYotpoData(); // Refresh panel data
    } catch (err) {
        console.error(err);
        message.error("Failed to adjust points.");
    } finally {
        adjustingPoints.value = false;
    }
}
</script>

<style scoped>
.history-table-wrapper {
    margin-top: 1rem;
    overflow-x: auto;
}
</style>
