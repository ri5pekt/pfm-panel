<!-- WorkTabsBar.vue -->
<template>
    <div class="work-tabs-bar">
        <n-tabs
            type="card"
            :value="currentValue"
            @update:value="(k) => $emit('change', k)"
            class="work-tabs"
        >
            <n-tab v-for="t in currentTabs" :key="t.key" :name="t.key" :tab="renderTab(t)" />
        </n-tabs>
    </div>
</template>

<script setup>
import { h, computed, unref } from "vue";

const props = defineProps({
    // Support being passed a Ref/ComputedRef as well (App.vue stores work tab state in refs).
    value: { type: [String, Number, Object], required: true },
    tabs: { type: [Array, Object], required: true }, // [{ key, label, closable }]
});

const emit = defineEmits(["change", "close"]);

const currentValue = computed(() => unref(props.value));
const currentTabs = computed(() => {
    const t = unref(props.tabs);
    return Array.isArray(t) ? t : [];
});

function renderTab(t) {
    return h(
        "div",
        { style: "display:flex;align-items:center;gap:8px;" },
        [
            h("span", { style: "cursor:pointer; user-select:none;" }, t.label),
            t.closable
                ? h(
                      "span",
                      {
                          title: "Close",
                          style: "cursor:pointer; user-select:none; font-weight:700; opacity:0.7; padding:0 4px; line-height:1;",
                          onClick: (e) => {
                              e.preventDefault();
                              e.stopPropagation();
                              emit("close", t.key);
                          },
                      },
                      "×"
                  )
                : null,
        ].filter(Boolean)
    );
}
</script>


