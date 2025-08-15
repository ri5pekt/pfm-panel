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

// Emits: update:dateRange — payload { from: "YYYY-MM-DD", to: "YYYY-MM-DD" } | null
const emit = defineEmits(["update:dateRange"]);

const props = defineProps({
    initialFrom: String,
    initialTo: String,
    titleLabel: { type: String, default: "Date" },
});

const showDropdown = ref(false);

/*──────────────── Helpers ───────────────────*/
function atLocalMidnight(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
}
function dateFromYMD(ymd) {
    if (!ymd) return null;
    const [y, m, d] = ymd.split("-").map(Number);
    return new Date(y, m - 1, d); // local date, no UTC shift
}
const formatISO = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};
function emitRange(f, t) {
    const ff = atLocalMidnight(f);
    const tt = atLocalMidnight(t);
    from.value = formatISO(ff);
    to.value = formatISO(tt);
    emit("update:dateRange", { from: from.value, to: to.value });
}

/*──────────────── Local state ───────────────*/
const from = ref(props.initialFrom || null);
const to = ref(props.initialTo || null);

const today = atLocalMidnight(new Date());
const customRange = ref([
    props.initialFrom ? atLocalMidnight(dateFromYMD(props.initialFrom)) : today,
    props.initialTo ? atLocalMidnight(dateFromYMD(props.initialTo)) : today,
]);

// Watch for parent prop changes (i.e. when filters update from outside)
watch(
    () => [props.initialFrom, props.initialTo],
    ([newFrom, newTo]) => {
        from.value = newFrom || null;
        to.value = newTo || null;
        if (newFrom && newTo) {
            customRange.value = [atLocalMidnight(dateFromYMD(newFrom)), atLocalMidnight(dateFromYMD(newTo))];
        }
    },
    { immediate: true }
);

/*──────────────── Presets / Dropdown handling ─────────*/
function handleSelect(key) {
    const base = atLocalMidnight(new Date());
    let f = base;
    let t = base;

    switch (key) {
        case "today":
            // f, t already today
            break;
        case "yesterday":
            f = new Date(base);
            f.setDate(f.getDate() - 1);
            t = new Date(base);
            t.setDate(t.getDate() - 1);
            break;
        case "last_7":
            f = new Date(base);
            f.setDate(f.getDate() - 6);
            t = base;
            break;
        case "last_30":
            f = new Date(base);
            f.setDate(f.getDate() - 29);
            t = base;
            break;
        case "this_month":
            f = atLocalMidnight(new Date(base.getFullYear(), base.getMonth(), 1));
            t = base;
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
    const [f, t] = value.map(atLocalMidnight);
    customRange.value = [f, t];
    emitRange(f, t);
    showDropdown.value = false;
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
                    // keep picker behavior local; no need to force startDate
                    showNowButton: false,
                }),
            ]);
        },
    },
];

/*──────────────── Read-only label ───────────*/
const label = computed(() => {
    if (from.value && to.value) {
        const fromDate = dateFromYMD(from.value);
        const toDate = dateFromYMD(to.value);
        const opts = { month: "short", day: "numeric" };

        return from.value === to.value
            ? fromDate.toLocaleDateString(undefined, opts)
            : `${fromDate.toLocaleDateString(undefined, opts)} - ${toDate.toLocaleDateString(undefined, opts)}`;
    }
    return "Select a date";
});
</script>
