// useRefund.js
import { ref, computed, watch } from "vue";
import { setCurrency } from "@/utils/utils";
import { request } from "@/utils/api";

/**
 * useRefund composable for order refunds.
 * @param {Object} params
 * @param {Ref|ComputedRef} params.order - The order object (should be a Vue ref or computed).
 * @param {String|Number} params.orderId - The order ID.
 * @param {Function} params.emit - The emit function from the component.
 * @param {Object} params.message - The Naive UI message instance.
 * @param {Function} params.formatCurrency - Currency formatting function.
 */
export function useRefund({ order, orderId, emit, message, formatCurrency }) {
    // ── refund-specific state ──
    const refundMode = ref(false);
    const showConfirmModal = ref(false);
    const isRefunding = ref(false);

    const refundItems = ref([]);
    const refundFees = ref([]);
    const refundShipping = ref([]);

    const pendingRefundItems = ref([]);
    const pendingRefundFees = ref([]);
    const pendingRefundShipping = ref([]);

    const refundReason = ref("");
    const refundPercent = ref();

    const isBraintree = computed(() => !!order.value?.payment_method?.includes("braintree"));
    const refundViaBraintree = ref(isBraintree.value);
    const userTouchedRefundVia = ref(false);

    watch(
        () => order.value?.payment_method,
        () => {
            if (!userTouchedRefundVia.value && !refundMode.value) {
                refundViaBraintree.value = isBraintree.value;
            }
        },
        { immediate: true }
    );

    watch(refundViaBraintree, () => {
        userTouchedRefundVia.value = true;
    });

    // Apply percent to all refund fields (line items, fees, shipping)
    function applyRefundPercent() {
        if (!refundPercent.value || refundPercent.value < 1 || refundPercent.value > 100) return;
        const percent = refundPercent.value / 100;

        // Line Items – exclude PPU
        refundItems.value = (order.value?.line_items || [])
            .filter((item) => {
                const isPPU = item.meta_data?.some((m) => m.key === "is_ppu" && m.value === "yes");
                return !isPPU;
            })
            .map((item) => ({
                id: item.id,
                //quantity: Math.floor(item.quantity * percent) || 0,
                total: +(item.total * percent).toFixed(2),
                tax: +(item.total_tax * percent).toFixed(2),
                transaction_id: getTransactionId(item),
            }));

        // Fees – skip all (they’ll remain at 0)
        refundFees.value = (order.value?.fee_lines || []).map((f) => ({
            id: f.id,
            total: 0,
            tax: 0,
        }));

        // Shipping – keep included, but you can skip if needed
        refundShipping.value = (order.value?.shipping_lines || []).map((s) => ({
            id: s.id,
            total: +(s.total * percent).toFixed(2),
            tax: +(s.total_tax * percent).toFixed(2),
        }));
    }

    // Automatically apply refund percent on input change
    function onRefundPercentInput(val) {
        if (!val || val < 1 || val > 100) {
            refundPercent.value = undefined;
            return;
        }
        applyRefundPercent();
    }

    // ── helpers ──
    function getRefundItem(id) {
        const item = refundItems.value.find((i) => i.id === id);
        return item || { id, quantity: 0, total: 0, tax: 0 };
    }
    function getRefundFee(id) {
        return refundFees.value.find((f) => f.id === id);
    }
    function getRefundShipping(id) {
        return refundShipping.value.find((s) => s.id === id);
    }

    function isPPU(item) {
        return item.meta_data?.some((m) => m.key === "is_ppu" && m.value === "yes");
    }
    function getTransactionId(item) {
        return item.meta_data?.find((m) => m.key === "transaction_id")?.value || null;
    }

    // ── currency helper ──
    watch(
        () => order.value?.currency,
        (c) => c && setCurrency(c),
        { immediate: true }
    );

    // ── computed totals ──
    const subtotal = computed(
        () => order.value?.line_items?.reduce((t, i) => t + +i.subtotal || 0, 0).toFixed(2) || "—"
    );
    const totalRefunded = computed(() => order.value?.refunds?.reduce((s, r) => s + Math.abs(+r.total || 0), 0) || 0);
    const refundTotalAmount = computed(() => {
        const items = refundItems.value.reduce((s, r) => s + +r.total + +r.tax, 0);
        const fees = refundFees.value.reduce((s, f) => s + +f.total + +f.tax, 0);
        const ship = refundShipping.value.reduce((s, s2) => s + +s2.total + +s2.tax, 0);
        return items + fees + ship;
    });
    const hasRefund = computed(
        () =>
            refundItems.value.some((i) => +i.quantity || +i.total || +i.tax) ||
            refundFees.value.some((f) => +f.total || +f.tax) ||
            refundShipping.value.some((s) => +s.total || +s.tax)
    );

    const totalAvailableToRefund = computed(() => {
        const total = parseFloat(order.value?.total || 0);
        return Math.max(0, total - totalRefunded.value);
    });

    const totalTaxAmount = computed(() =>
        order.value?.tax_lines?.reduce((sum, tax) => sum + parseFloat(tax.tax_total || 0), 0).toFixed(2)
    );

    // ── refund mode management ──
    function enterRefundMode() {
        refundMode.value = true;
        refundItems.value = (order.value?.line_items || []).map((i) => ({
            id: i.id,
            quantity: 0,
            total: 0,
            tax: 0,
            transaction_id: getTransactionId(i),
        }));
        refundFees.value = (order.value?.fee_lines || []).map((f) => ({
            id: f.id,
            total: 0,
            tax: 0,
        }));
        refundShipping.value = (order.value?.shipping_lines || []).map((s) => ({
            id: s.id,
            total: 0,
            tax: 0,
        }));
    }
    function cancelRefund() {
        refundMode.value = false;
        refundItems.value = [];
        refundFees.value = [];
        refundShipping.value = [];
    }

    function onRefundQuantityChange(id, item) {
        const r = getRefundItem(id);
        const q = +r.quantity || 0;
        const unitTotal = +item.total / +item.quantity || 0;
        const unitTax = +item.total_tax / +item.quantity || 0;
        r.total = +(unitTotal * q).toFixed(2);
        r.tax = +(unitTax * q).toFixed(2);
    }
    function onTotalOrTaxChange(id, item) {
        const r = getRefundItem(id);
        const q = +r.quantity || 0;
        const unitTotal = +item.total / +item.quantity || 0;
        const unitTax = +item.total_tax / +item.quantity || 0;
        const expTotal = +(unitTotal * q).toFixed(2);
        const expTax = +(unitTax * q).toFixed(2);
        if (Math.abs(r.total - expTotal) > 0.01 || Math.abs(r.tax - expTax) > 0.01) r.quantity = 0;
    }

    // ── quick helpers ──
    function refundAllItems() {
        refundItems.value = (order.value?.line_items || []).map((i) => ({
            id: i.id,
            quantity: i.quantity,
            total: +i.total,
            tax: +i.total_tax,
            transaction_id: getTransactionId(i),
        }));
    }
    function refundAllFeesAndShipping() {
        refundFees.value = (order.value?.fee_lines || []).map((f) => ({
            id: f.id,
            total: +f.total,
            tax: +f.total_tax,
        }));
        refundShipping.value = (order.value?.shipping_lines || []).map((s) => ({
            id: s.id,
            total: +s.total,
            tax: +s.total_tax,
        }));
    }
    function resetRefundItems() {
        refundItems.value = refundItems.value.map((i) => ({
            ...i,
            quantity: 0,
            total: 0,
            tax: 0,
        }));
    }
    function resetFeesAndShipping() {
        refundFees.value = refundFees.value.map((f) => ({
            ...f,
            total: 0,
            tax: 0,
        }));
        refundShipping.value = refundShipping.value.map((s) => ({
            ...s,
            total: 0,
            tax: 0,
        }));
    }

    // ── user confirmation ──
    function confirmRefund() {
        pendingRefundItems.value = refundItems.value.filter((i) => +i.quantity || +i.total || +i.tax);
        pendingRefundFees.value = refundFees.value.filter((f) => +f.total || +f.tax);
        pendingRefundShipping.value = refundShipping.value.filter((s) => +s.total || +s.tax);

        if (
            !pendingRefundItems.value.length &&
            !pendingRefundFees.value.length &&
            !pendingRefundShipping.value.length
        ) {
            message.warning("No items, fees, or shipping selected for refund");
            return;
        }
        showConfirmModal.value = true;
    }

    // ── refund API call ──
    async function sendRefund() {
        isRefunding.value = true;
        showConfirmModal.value = false;

        try {
            const payload = {
                items: pendingRefundItems.value.map((i) => ({
                    id: i.id,
                    quantity: i.quantity,
                    total: i.total,
                    tax: i.tax,
                    ...(i.transaction_id ? { transaction_id: i.transaction_id } : {}),
                })),
                fees: pendingRefundFees.value,
                shipping: pendingRefundShipping.value,
                refund_via_braintree: isBraintree.value && refundViaBraintree.value ? 1 : 0,
                reason: refundReason.value,
            };
            console.log("Refund payload:", payload);
            const res = await request({
                url: `/orders/${orderId}/refund`,
                method: "POST",
                body: payload,
                raw: true, // manually parse response text
            });

            const txt = await res.text();
            if (!res.ok) {
                let errMsg = "Refund failed";
                try {
                    const json = JSON.parse(txt);
                    errMsg = json.error || json.message || "Unknown error";
                } catch {
                    errMsg = txt;
                }
                throw new Error(errMsg);
            }

            message.success("Refund processed successfully!");
            cancelRefund();
            pendingRefundItems.value = [];
            pendingRefundFees.value = [];
            pendingRefundShipping.value = [];
            emit("updateOrder");
        } catch (e) {
            message.error(`Refund failed: ${e.message}`);
            // eslint-disable-next-line no-console
            console.error(e);
        } finally {
            isRefunding.value = false;
        }
    }

    return {
        refundMode,
        showConfirmModal,
        isRefunding,
        refundItems,
        refundFees,
        refundShipping,
        pendingRefundItems,
        pendingRefundFees,
        pendingRefundShipping,
        refundViaBraintree,

        getRefundItem,
        getRefundFee,
        getRefundShipping,
        isPPU,
        enterRefundMode,
        cancelRefund,
        onRefundQuantityChange,
        onTotalOrTaxChange,
        refundAllItems,
        refundAllFeesAndShipping,
        resetRefundItems,
        resetFeesAndShipping,
        confirmRefund,
        sendRefund,

        subtotal,
        totalRefunded,
        refundTotalAmount,
        hasRefund,
        totalAvailableToRefund,
        totalTaxAmount,

        refundPercent,
        onRefundPercentInput,
        applyRefundPercent,
        refundReason,
    };
}
