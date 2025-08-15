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
import { request } from "@/utils/api";

const props = defineProps({
    orderId: String,
    refreshKey: [String, Number],
    sourceType: { type: String, default: "order" },
});

const orderNotes = ref([]);
const loadingNotes = ref(true);

const endpoint = computed(() =>
    props.sourceType === "replacement" ? `/replacements/${props.orderId}/notes` : `/orders/${props.orderId}/notes`
);

async function fetchNotes() {
    if (!props.orderId) return;
    loadingNotes.value = true;
    try {
        orderNotes.value = await request({ url: endpoint.value, useCustomApi: true });
    } finally {
        loadingNotes.value = false;
    }
}

watch([() => props.orderId, () => props.refreshKey], fetchNotes, { immediate: true });
</script>
