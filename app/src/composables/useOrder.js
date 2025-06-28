// useOrder.js
import { ref, computed, watch } from "vue";
import { request } from "@/utils/api";
import { formatOrderDate, setCurrency } from "@/utils/utils";

export function useOrder(orderIdRef) {
    const order = ref(null);
    const loadingOrder = ref(true);

    const fetchOrder = async () => {
        const orderId = orderIdRef.value;
        if (!orderId) return;

        loadingOrder.value = true;
        try {
            const json = await request({
                url: `/orders/${orderId}`,
            });

            order.value = json;
            console.log("✅ Order loaded:", json);
        } catch (err) {
            console.error("❌ Failed to fetch order", err);
        } finally {
            loadingOrder.value = false;
        }
    };

    // 🧠 Reactively re-fetch when order ID changes
    watch(
        orderIdRef,
        () => {
            fetchOrder();
        },
        { immediate: true }
    );

    // 🎯 Automatically update currency when order changes
    watch(
        () => order.value?.currency,
        (currency) => {
            if (currency) setCurrency(currency);
        },
        { immediate: true }
    );

    // 🎁 Extras
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

    return {
        order,
        loadingOrder,
        fetchOrder,
        formattedCreatedDate,
        getMeta,
        trackingNumber,
    };
}
