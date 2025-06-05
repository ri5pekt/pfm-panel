<template>
    <div class="date-range-filter">
        <n-text depth="3">Date</n-text>
        <n-dropdown trigger="click" :options="options" @select="handleSelect">
            <n-button style="justify-content: flex-start; width: 150px">
                {{ label }}
            </n-button>
        </n-dropdown>
    </div>
</template>

<script setup>
import { ref, computed, h } from "vue";
import Datepicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

// Emits:   update:dateRange  — payload { from: "YYYY-MM-DD", to: "YYYY-MM-DD" } | null
const emit = defineEmits(["update:dateRange"]);

const props = defineProps({
    initialFrom: String,
    initialTo: String,
});

/*──────────────── Local state ───────────────*/
const customRange = ref([new Date(), new Date()]);

/*──────────────── Helpers ───────────────────*/
const formatISO = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};

function emitRange(from, to) {
    emit("update:dateRange", { from: formatISO(from), to: formatISO(to) });
}

/*──────────────── Dropdown handling ─────────*/
function handleSelect(key) {
    const now = new Date();
    let from, to;

    switch (key) {
        case "today":
            from = to = now;
            break;
        case "yesterday":
            from = to = new Date(now.setDate(now.getDate() - 1));
            break;
        case "last_7":
            from = new Date(now.setDate(now.getDate() - 6));
            to = new Date();
            break;
        case "last_30":
            from = new Date(now.setDate(now.getDate() - 29));
            to = new Date();
            break;
        case "this_month":
            from = new Date(now.getFullYear(), now.getMonth(), 1);
            to = new Date();
            break;
        case "all_time":
            emit("update:dateRange", null);
            return;
        case "custom":
            return; // handled in Datepicker callback
    }
    customRange.value = [from, to];
    emitRange(from, to);
}

function applyCustomRange(value) {
    if (!Array.isArray(value) || value.length !== 2) return;
    // keep the picker in sync with the latest choice
    customRange.value = value;
    const [from, to] = value;
    if (from && to) emitRange(from, to);
}

/*──────────────── Dropdown options ──────────*/
const options = [
    { label: "All Time", key: "all_time" },
    { label: "Today", key: "today" },
    { label: "Yesterday", key: "yesterday" },
    { label: "Last 7 Days", key: "last_7" },
    { label: "Last 30 Days", key: "last_30" },
    { label: "This Month", key: "this_month" },
    {
        key: "custom",
        type: "render",
        render() {
            return h("div", { style: "padding: 8px 12px; width: 300px;" }, [
                h(Datepicker, {
                    modelValue: customRange.value,
                    "onUpdate:modelValue": applyCustomRange,
                    range: true,
                    format: "dd/MM/yy",
                    previewFormat: "dd/MM/yy",
                    enableTimePicker: false,
                    autoApply: true,
                    startDate: new Date(),
                    showNowButton: false,
                }),
            ]);
        },
    },
];

/*──────────────── Read‑only label ───────────*/
const label = computed(() => {
    if (props.initialFrom && props.initialTo) {
        const from = new Date(props.initialFrom);
        const to = new Date(props.initialTo);
        const opts = { month: "short", day: "numeric" };

        return props.initialFrom === props.initialTo
            ? from.toLocaleDateString(undefined, opts)
            : `${from.toLocaleDateString(undefined, opts)} - ${to.toLocaleDateString(undefined, opts)}`;
    }
    return "Select a date";
});
</script>

<style scoped>
.date-range-filter {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
</style>
