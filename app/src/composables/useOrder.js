// useOrder.js
import { ref, computed, watch } from "vue";
import { request } from "@/utils/api";
import { formatOrderDate, setCurrency } from "@/utils/utils";
import { useRoute, useRouter } from "vue-router";
import { useMessage, useDialog } from "naive-ui";

export function useOrder(orderIdRef, sourceType = "order") {
    const order = ref(null);
    const loadingOrder = ref(true);
    const route = useRoute();
    const router = useRouter();
    const dialog = useDialog();
    const message = useMessage();
    const isReplacement = sourceType === "replacement";

    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const fetchOrder = async (retryCount = 1) => {
        const orderId = orderIdRef.value;
        if (!orderId) return;

        loadingOrder.value = true;
        try {
            const params = {};
            if (route.query.is_archived) params.is_archived = route.query.is_archived;
            const endpoint = sourceType === "replacement" ? `/replacements/${orderId}` : `/orders/${orderId}`;
            console.log(`🔍 Fetching ${sourceType} with ID:`, orderId, "Params:", params);

            const json = await request({
                url: endpoint,
                params,
            });

            order.value = json;
            console.log("✅ Order loaded:", json);
        } catch (err) {
            console.error("❌ Failed to fetch order", err);
            if (retryCount > 0) {
                console.log(`🔁 Retrying fetchOrder in 1 second... (${retryCount} retries left)`);
                await wait(1000);
                await fetchOrder(retryCount - 1);
            }
        } finally {
            loadingOrder.value = false;
        }
    };

    // 🎯 Reactively re-fetch when order ID changes
    watch(
        orderIdRef,
        () => {
            fetchOrder();
        },
        { immediate: true }
    );

    // 🪙 Automatically update currency
    watch(
        () => order.value?.currency,
        (currency) => {
            if (currency) setCurrency(currency);
        },
        { immediate: true }
    );

    // 💡 Meta utils
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

    // 🧨 New: Create Replacement
    const createReplacement = () => {
        const id = orderIdRef.value;
        if (!id) return;

        const loadingRef = ref(false);

        dialog.warning({
            title: "Create Replacement?",
            content: `Create a replacement for order #${id}?`,
            positiveText: "Yes",
            negativeText: "Cancel",
            positiveButtonProps: {
                type: "success",
                loading: loadingRef,
            },
            async onPositiveClick() {
                loadingRef.value = true;

                const payload = {
                    order_id: parseInt(id),
                };

                console.log("[createReplacement] Sending payload:", JSON.stringify(payload, null, 2));

                try {
                    const res = await request({
                        method: "POST",
                        url: "/replacements",
                        body: payload, // 💥 Just like in your working pattern
                    });

                    console.log("[createReplacement] Response:", res);

                    if (res?.id) {
                        message.success("Replacement created.");
                        router.push(`/replacements/${res.id}`);
                    } else {
                        message.error("Failed to create replacement.");
                    }
                } catch (err) {
                    console.error("💥 Replacement creation failed:", err);
                    message.error("Something went wrong.");
                } finally {
                    loadingRef.value = false;
                }
            },
        });
    };

    // 📧 New: Resend order email (processing/completed/invoice)
    const resendEmail = (type = "processing") => {
        const id = orderIdRef.value;
        if (!id) return;
        if (route.query.is_archived === "1" || route.query.is_archived === "true") {
            message.warning("Cannot resend email for archived orders.");
            return;
        }
        if (isReplacement) {
            message.warning("Email resend is for live orders only.");
            return;
        }

        const loadingRef = ref(false);
        const typeLabel = (t) =>
            ({ processing: "Processing", completed: "Completed", invoice: "Invoice" }[t] || "Processing");

        dialog.warning({
            title: "Resend order email?",
            content: `Send the "${typeLabel(type)}" email to ${
                order.value?.billing?.email || "the customer"
            } for order #${id}?`,
            positiveText: "Send",
            negativeText: "Cancel",
            positiveButtonProps: { loading: loadingRef },
            async onPositiveClick() {
                loadingRef.value = true;
                try {
                    const res = await request({
                        url: `/orders/${id}/resend-email`,
                        method: "POST",
                        body: { type }, // "processing" | "completed" | "invoice"
                    });
                    if (res?.success) {
                        message.success("Email resent to customer.");
                    } else {
                        throw new Error(res?.message || "Request failed");
                    }
                } catch (e) {
                    message.error(`Failed to resend email: ${e.message || e}`);
                } finally {
                    loadingRef.value = false;
                }
            },
        });
    };

    return {
        order,
        loadingOrder,
        fetchOrder,
        formattedCreatedDate,
        getMeta,
        trackingNumber,
        createReplacement,
        resendEmail,
    };
}
