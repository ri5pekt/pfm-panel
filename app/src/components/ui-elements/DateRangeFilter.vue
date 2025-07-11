<!-- DateRangeFilter.vue -->

<template>
    <div class="date-range-filter">
        <n-text depth="3">{{ titleLabel }}</n-text>
        <n-dropdown trigger="click" :options="options" @select="handleSelect" v-model:show="showDropdown">
            <n-button style="justify-content: flex-start; width: 150px">
                {{ label }}
            </n-button>
        </n-dropdown>
    </div>
</template>

<script setup>
import { ref, computed, h, watch } from "vue";
import Datepicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

// Emits:   update:dateRange  — payload { from: "YYYY-MM-DD", to: "YYYY-MM-DD" } | null
const emit = defineEmits(["update:dateRange"]);

const props = defineProps({
    initialFrom: String,
    initialTo: String,
    titleLabel: { type: String, default: "Date" }
});

const showDropdown = ref(false);

/*──────────────── Local state ───────────────*/
// Use local reactive state for current from/to
const from = ref(props.initialFrom || null);
const to = ref(props.initialTo || null);

// Keep Datepicker in sync
const customRange = ref([
    props.initialFrom ? new Date(props.initialFrom) : new Date(),
    props.initialTo ? new Date(props.initialTo) : new Date(),
]);

// Watch for parent prop changes (i.e. when filters update from outside)
watch(
    () => [props.initialFrom, props.initialTo],
    ([newFrom, newTo]) => {
        from.value = newFrom;
        to.value = newTo;
        if (newFrom && newTo) {
            customRange.value = [new Date(newFrom), new Date(newTo)];
        }
    },
    { immediate: true }
);

/*──────────────── Helpers ───────────────────*/
const formatISO = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};

function emitRange(f, t) {
    from.value = formatISO(f);
    to.value = formatISO(t);
    emit("update:dateRange", { from: from.value, to: to.value });
}

/*──────────────── Dropdown handling ─────────*/
function handleSelect(key) {
    const now = new Date();
    let f, t;

    switch (key) {
        case "today":
            f = t = new Date();
            break;
        case "yesterday":
            f = t = new Date();
            f.setDate(f.getDate() - 1);
            t.setDate(t.getDate() - 1);
            break;
        case "last_7":
            f = new Date();
            t = new Date();
            f.setDate(f.getDate() - 6);
            break;
        case "last_30":
            f = new Date();
            t = new Date();
            f.setDate(f.getDate() - 29);
            break;
        case "this_month":
            f = new Date(now.getFullYear(), now.getMonth(), 1);
            t = new Date();
            break;
        case "all_time":
            from.value = null;
            to.value = null;
            emit("update:dateRange", null);
            return;
        case "custom":
            return; // handled in Datepicker callback
    }
    customRange.value = [f, t];
    emitRange(f, t);
}

function applyCustomRange(value) {
    if (!Array.isArray(value) || value.length !== 2) return;
    customRange.value = value;
    const [f, t] = value;
    if (f && t) {
        emitRange(f, t);
        showDropdown.value = false;
    }
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
    if (from.value && to.value) {
        const fromDate = new Date(from.value);
        const toDate = new Date(to.value);
        const opts = { month: "short", day: "numeric" };

        return from.value === to.value
            ? fromDate.toLocaleDateString(undefined, opts)
            : `${fromDate.toLocaleDateString(undefined, opts)} - ${toDate.toLocaleDateString(undefined, opts)}`;
    }
    return "Select a date";
});
</script>
