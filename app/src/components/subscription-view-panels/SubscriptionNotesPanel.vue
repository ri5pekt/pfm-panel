<template>
    <div class="panel notes-panel">
        <h3>Subscription Notes</h3>
        <n-skeleton v-if="loadingNotes" text :repeat="3" />
        <div v-else class="notes-scroll">
            <div v-for="note in subscriptionNotes" :key="note.id" class="note">
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

const props = defineProps({
    subscriptionId: String,
    refreshKey: [String, Number],
});

const subscriptionNotes = ref([]);
const loadingNotes = ref(true);

async function fetchNotes() {
    if (!props.subscriptionId) return;
    loadingNotes.value = true;

    try {
        subscriptionNotes.value = await request({
            url: `/subscriptions/${props.subscriptionId}/notes`,
            useCustomApi: true,
        });
    } catch (err) {
        console.error("❌ Failed to load subscription notes:", err);
    } finally {
        loadingNotes.value = false;
    }
}

watch([() => props.subscriptionId, () => props.refreshKey], fetchNotes, {
    immediate: true,
});
</script>
