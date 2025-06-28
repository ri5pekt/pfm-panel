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
import { ref, watch } from "vue";
import { request } from "@/utils/api";

// ✅ Props
const props = defineProps({
    orderId: String,
    refreshKey: [String, Number],
});

// ✅ State
const orderNotes = ref([]);
const loadingNotes = ref(true);

// ✅ Fetch function
async function fetchNotes() {
    console.log("Fetching order notes for ID:", props.orderId);
    if (!props.orderId) return;
    loadingNotes.value = true;

    try {
        orderNotes.value = await request({
            url: `/orders/${props.orderId}/notes`,
            useCustomApi: true, // Now this uses your custom endpoint!
        });
    } catch (err) {
        console.error("❌ Failed to load order notes:", err);
    } finally {
        console.log("✅ Order notes loaded:", orderNotes.value.length);
        loadingNotes.value = false;
    }
}

// ✅ Watch orderId for changes and fetch on load
watch([() => props.orderId, () => props.refreshKey], fetchNotes, {
    immediate: true,
});
</script>
