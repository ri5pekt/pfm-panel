<!-- OrderNotesPanel.vue -->
<template>
    <div class="panel notes-panel">
        <h3>Order Notes</h3>
        <n-skeleton v-if="loadingNotes" text :repeat="3" />
        <div v-else class="notes-scroll">
            <div v-for="note in orderNotes" :key="note.id" class="note">
                <div class="note-text" v-html="note.note"></div>
                <p class="note-meta">
                    <abbr :title="note.date_created" class="exact-date">
                        {{ new Date(note.date_created).toLocaleString() }}
                    </abbr>
                    <span v-if="note.author"> — {{ note.author }}</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { useRoute } from "vue-router";
import { request } from "@/utils/api";

const props = defineProps({
    orderId: String,
    refreshKey: [String, Number],
    sourceType: { type: String, default: "order" },
});

const orderNotes = ref([]);
const loadingNotes = ref(true);
const loadedFor = ref(null); // `${sourceType}:${orderId}`
const loadedRefreshKey = ref(null);
const route = useRoute();

const endpoint = computed(() =>
    props.sourceType === "replacement" ? `/replacements/${props.orderId}/notes` : `/orders/${props.orderId}/notes`
);

async function fetchNotes() {
    if (!props.orderId) return;

    // Guard against KeepAlive side-effects: cached, inactive views still observe route changes.
    // Only fetch if we're on the matching route for this notes panel.
    if (props.sourceType === "replacement") {
        if (route.name !== "replacement-view") return;
    } else {
        if (route.name !== "order-view") return;
    }

    const cacheKey = `${props.sourceType}:${props.orderId}`;
    const rk = props.refreshKey ?? null;
    // If we already loaded notes for this tab and nothing requested a refresh, don't refetch (keeps tab state stable).
    if (loadedFor.value === cacheKey && loadedRefreshKey.value === rk && orderNotes.value.length) {
        return;
    }

    loadingNotes.value = orderNotes.value.length === 0;
    try {
        orderNotes.value = await request({ url: endpoint.value, useCustomApi: true });
        loadedFor.value = cacheKey;
        loadedRefreshKey.value = rk;
    } catch (err) {
        // Avoid "Uncaught (in promise)" noise; show empty notes if not found (common when IDs mismatch)
        console.warn("Failed to load order notes:", err);
        orderNotes.value = orderNotes.value || [];
    } finally {
        loadingNotes.value = false;
    }
}

watch([() => props.orderId, () => props.refreshKey, () => props.sourceType], fetchNotes, { immediate: true });
</script>
