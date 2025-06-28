
<!-- SubscriptionView.vue -->
<template>
    <div class="subscription-view">
        <div class="page-top">
            <n-button @click="router.back()">← Back to Subscriptions</n-button>
            <div class="page-title">Subscription #{{ props.id }}</div>
        </div>
        <div class="subscription-grid">
            <!-- Subscription Info Panel -->
            <SubscriptionInfoPanel
                :subscription="subscription"
                :loading="loadingSubscription"
                @update-subscription="handleSubscriptionUpdate"
            />
            <!-- Subscription Notes Panel -->
            <SubscriptionNotesPanel :subscriptionId="props.id" :refreshKey="notesRefreshKey" />

            <SubscriptionTotalsPanel
                :subscription="subscription"
                :subscriptionId="props.id"
                :loading="loadingSubscription"
                @update-subscription="handleSubscriptionUpdate"
            />

            <!-- Subscription Details Panel (NEW - reused from order view) -->
            <SubscriptionPanel :branch="subscription?.branch" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMessage } from "naive-ui";

// Panels
import SubscriptionInfoPanel from "@/components/subscription-view-panels/SubscriptionInfoPanel.vue";
import SubscriptionNotesPanel from "@/components/subscription-view-panels/SubscriptionNotesPanel.vue";
import SubscriptionTotalsPanel from "@/components/subscription-view-panels/SubscriptionTotalsPanel.vue";
import SubscriptionPanel from "@/components/order-view-panels/SubscriptionPanel.vue";
// (Import other panels as needed)

import { useSubscription } from "@/composables/useSubscription";

const props = defineProps({
    id: String,
});

const subscriptionId = computed(() => props.id);

const { subscription, loadingSubscription, fetchSubscription } = useSubscription(subscriptionId);

const notesRefreshKey = ref(0);
function refreshNotes() {
    notesRefreshKey.value = Date.now();
}

function handleSubscriptionUpdate() {
    fetchSubscription();
    refreshNotes();
}

const message = useMessage();
const route = useRoute();
const router = useRouter();
</script>
