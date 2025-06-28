import { ref, computed, watch } from "vue";
import { request } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";

export function useSubscription(subscriptionIdRef) {
    const subscription = ref(null);
    const loadingSubscription = ref(true);

    const fetchSubscription = async () => {
        const subscriptionId = subscriptionIdRef.value;
        if (!subscriptionId) return;

        loadingSubscription.value = true;
        try {
            const json = await request({
                url: `/subscriptions/${subscriptionId}`,
            });

            subscription.value = json;
            console.log("✅ Subscription loaded:", json);
        } catch (err) {
            console.error("❌ Failed to fetch subscription", err);
        } finally {
            loadingSubscription.value = false;
        }
    };

    // Reactively re-fetch when subscription ID changes
    watch(
        subscriptionIdRef,
        () => {
            fetchSubscription();
        },
        { immediate: true }
    );

    // Format date for display if needed (optional utility)
    const formattedCreatedDate = computed(() => {
        const raw = subscription.value?.start_date || "";
        return formatOrderDate(raw, true);
    });

    return {
        subscription,
        loadingSubscription,
        fetchSubscription,
        formattedCreatedDate,
    };
}
