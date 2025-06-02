import { ref, computed, watch, onMounted } from "vue";
import { apiBaseCustom, authHeader } from "@/utils/api";
import { formatOrderDate, setCurrency } from "@/utils/utils";
import { useRoute } from "vue-router";

export function useOrder(orderId) {
    const order = ref(null);
    const loadingOrder = ref(true);
    const route = useRoute();

 
    const fetchOrder = async () => {
        loadingOrder.value = true;
        try {
            const res = await fetch(`${apiBaseCustom}/orders/${orderId}`, {
                headers: { Authorization: authHeader },
            });
            const json = await res.json();
            order.value = json;
            console.log("✅ Order loaded:", json);
        } catch (err) {
            console.error("Failed to fetch order", err);
        } finally {
            loadingOrder.value = false;
        }
    };

    const formattedCreatedDate = computed(() => {
        const raw = order.value?.date_created?.date || "";
        return formatOrderDate(raw, true);
    });

    const getMeta = (key) => {
        const meta = order.value?.meta_data?.find((m) => m.key === key);
        return meta?.value;
    };

    const trackingNumber = computed(() => {
        const trackingMeta = getMeta("_wc_shipment_tracking_items");
        return Array.isArray(trackingMeta) && trackingMeta.length ? trackingMeta[0].tracking_number : null;
    });

    // update currency when order changes
    watch(
        () => order.value?.currency,
        (currency) => {
            if (currency) setCurrency(currency);
        },
        { immediate: true }
    );

    // auto-fetch when mounted
    onMounted(fetchOrder);

    // expose everything
    return {
        order,
        loadingOrder,
        fetchOrder,
        formattedCreatedDate,
        getMeta,
        trackingNumber,
    };
}
