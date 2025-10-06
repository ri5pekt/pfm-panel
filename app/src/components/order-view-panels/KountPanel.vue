<!-- KountPanel.vue -->
<template>
    <div class="panel kount-panel">
        <h3>Kount Response</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <div class="kount-grid">
                <div class="field"><strong>Omniscore:</strong> {{ omniscore ?? "—" }}</div>

                <div class="field">
                    <strong>Response:</strong>
                    <n-tag :type="responseTagType" size="small">
                        {{ responseCode || "—" }}
                        <span v-if="responseLabel"> · {{ responseLabel }}</span>
                    </n-tag>
                </div>

                <div class="field">
                    <strong>Transaction ID:</strong>
                    <span v-if="transactionId">
                        <a
                            :href="`https://app.kount.com/event-analysis/order/${transactionId}`"
                            target="_blank"
                            rel="noopener"
                            >{{ transactionId }}</a
                        >
                    </span>
                    <span v-else>—</span>
                </div>

                <div class="field">
                    <strong>KAPT:</strong>
                    <n-tag :type="kaptTagType" size="small">{{ kapt ?? "—" }}</n-tag>
                </div>

                <div class="field"><strong>Cards:</strong> {{ cards ?? "—" }}</div>

                <div class="field"><strong>Emails:</strong> {{ emails ?? "—" }}</div>

                <div class="field"><strong>Devices:</strong> {{ devices ?? "—" }}</div>

                <div class="field span-2">
                    <strong>Triggered Rules:</strong>
                    <span v-if="triggeredRulesReady.length">
                        <ul class="rules-list">
                            <li v-for="(r, idx) in triggeredRulesReady" :key="idx">
                                <code v-if="r.ID">#{{ r.ID }}</code>
                                <span v-if="r.DESCRIPTION"> — {{ r.DESCRIPTION }}</span>
                            </li>
                        </ul>
                    </span>
                    <span v-else>—</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { NTag, NSkeleton } from "naive-ui";

/**
 * We read from meta already loaded into the order.
 * Prefer a passed getMeta(key) (like your other panels).
 */
const props = defineProps({
    loading: Boolean,
    getMeta: { type: Function, required: true }, // same pattern as WarehouseExportPanel
    order: { type: Object, default: null }, // optional fallback if needed
});

function meta(key) {
    // Primary: composable getMeta coming from OrderView
    if (typeof props.getMeta === "function") return props.getMeta(key);

    // Fallback if ever used standalone with raw order data
    const arr = props.order?.meta_data ?? [];
    const hit = arr.find((m) => m.key === key);
    return hit ? hit.value : null;
}

/* ---- Extract fields from meta ---- */
const omniscore = computed(() => toNumber(meta("kount_RIS_omniscore") ?? meta("kount_RIS_omniscore".toLowerCase())));
const responseCode = computed(() => (meta("kount_RIS_response") ?? "").toString().trim());
const transactionId = computed(() => (meta("kount_transaction_id") ?? "").toString().trim());
const kapt = computed(() => (meta("kount_KAPT") ?? meta("kount_kapt") ?? "").toString().trim() || null);
const cards = computed(() => meta("kount_CARDS") ?? meta("kount_cards"));
const emails = computed(() => meta("kount_EMAIL") ?? meta("kount_email"));
const devices = computed(() => meta("kount_DEVICES") ?? meta("kount_devices"));

/* Triggered rules can be an empty string or a JSON string */
const triggeredRulesRaw = computed(() => meta("kount_TRIGGERED_RULES") ?? meta("kount_triggered_rules") ?? "");
const triggeredRulesReady = computed(() => {
    const v = (triggeredRulesRaw.value || "").toString().trim();
    if (!v) return [];
    try {
        const parsed = JSON.parse(v);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        // sometimes it's a single object or weirdly encoded >; try a light cleanup
        try {
            const repaired = v.replaceAll("\\u003e", ">"); // cosmetic
            const maybeArray = JSON.parse(repaired);
            return Array.isArray(maybeArray) ? maybeArray : [];
        } catch {
            return [];
        }
    }
});

/* ---- Display helpers ---- */
function toNumber(x) {
    const n = Number(x);
    return Number.isFinite(n) ? n : null;
}

const responseLabelMap = {
    A: "Approve",
    D: "Decline",
    R: "Review",
};
const responseLabel = computed(() => responseLabelMap[responseCode.value] || null);
const responseTagType = computed(() => {
    const code = responseCode.value;
    return code === "A" ? "success" : code === "R" ? "warning" : code === "D" ? "error" : "default";
});

const kaptTagType = computed(() => (kapt.value?.toUpperCase() === "Y" ? "success" : "default"));
</script>

<style scoped>
.kount-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 20px; /* row gap / column gap */
    font-size: 13px;
}
.field {
    display: flex;
    align-items: center;
    gap: 4px;
}
.field strong {
    font-weight: 600;
    white-space: nowrap;
}
.field.span-2 {
    grid-column: span 2;
}
.rules-list {
    margin: 0;
    padding-left: 1.2rem;
}
</style>
