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
import { useRoute } from "vue-router";
import { request } from "@/utils/api";

const props = defineProps({
    subscriptionId: String,
    refreshKey: [String, Number],
});

const subscriptionNotes = ref([]);
const loadingNotes = ref(true);
const loadedFor = ref(null); // subscriptionId
const loadedRefreshKey = ref(null);
const route = useRoute();

async function fetchNotes() {
    if (!props.subscriptionId) return;
    if (route.name !== "subscription-view") return;

    const rk = props.refreshKey ?? null;
    if (loadedFor.value === props.subscriptionId && loadedRefreshKey.value === rk && subscriptionNotes.value.length) {
        return;
    }

    loadingNotes.value = subscriptionNotes.value.length === 0;

    try {
        subscriptionNotes.value = await request({
            url: `/subscriptions/${props.subscriptionId}/notes`,
            useCustomApi: true,
        });
        loadedFor.value = props.subscriptionId;
        loadedRefreshKey.value = rk;
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
