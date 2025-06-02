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
import { ref, onMounted } from "vue";
import { apiBase, authHeader } from "@/utils/api";

const { orderId } = defineProps({
    orderId: {
        type: String,
        required: true,
    },
});

const orderNotes = ref([]);
const loadingNotes = ref(true);

async function fetchNotes() {
    loadingNotes.value = true;
    try {
        const res = await fetch(`${apiBase}/orders/${orderId}/notes`, {
            headers: { Authorization: authHeader },
        });
        orderNotes.value = await res.json();
    } catch (err) {
        console.error("Failed to load order notes:", err);
    } finally {
        loadingNotes.value = false;
    }
}

onMounted(fetchNotes);
</script>
