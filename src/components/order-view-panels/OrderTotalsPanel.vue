<!-- src/components/order-view-panels/OrderTotalsPanel.vue -->
<template>
    <div class="panel totals-panel">
        <!-- Confirm refund modal -->
        <n-modal v-model:show="showConfirmModal" preset="dialog" type="warning" title="Confirm Refund">
            <template #default>
                Are you sure you want to process this refund?
                <br />
                This action will be logged and cannot be undone.
            </template>
            <template #action>
                <n-button ghost @click="showConfirmModal = false">Cancel</n-button>
                <n-button type="primary" :loading="isRefunding" @click="sendRefund">Proceed</n-button>
            </template>
        </n-modal>

        <h3>Order Totals</h3>
        <n-skeleton v-if="loading" text :repeat="5" />

        <div v-else class="totals-list">
            <!-- ─────────── Products ─────────── -->
            <h4>Products</h4>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Cost</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.line_items" :key="item.id">
                        <!-- Product Info -->
                        <td>
                            <div class="product-cell">
                                <img
                                    :src="item.image?.src || 'https://via.placeholder.com/48'"
                                    alt="Product image"
                                    class="product-image"
                                />
                                <div>
                                    <div style="display: flex; align-items: center; gap: 6px">
                                        <strong>{{ item.name }}</strong>
                                        <n-tag v-if="isPPU(item)" size="small" type="warning" bordered>PPU</n-tag>
                                    </div>
                                    <small v-if="item.sku">SKU: {{ item.sku }}</small>
                                </div>
                            </div>
                        </td>

                        <!-- Unit Cost -->
                        <td>{{ formatCurrency(item.subtotal / item.quantity) }}</td>

                        <!-- Quantity -->
                        <td>
                            <div v-if="refundMode">
                                <div>× {{ item.quantity }}</div>
                                <n-input-number
                                    v-model:value="getRefundItem(item.id).quantity"
                                    :min="0"
                                    :max="item.quantity"
                                    @update:value="onQuantityChange(item.id, item)"
                                    size="small"
                                    style="width: 70px"
                                />
                            </div>
                            <div v-else>
                                {{ item.quantity }}
                            </div>
                            <div v-if="item.qty_refunded > 0" class="refunded-qty">↩ -{{ item.qty_refunded }}</div>
                        </td>

                        <!-- Total -->
                        <td>
                            <div v-if="refundMode">
                                <div>{{ formatCurrency(item.total) }}</div>
                                <n-input-number
                                    v-model:value="getRefundItem(item.id).total"
                                    :min="0"
                                    :max="item.total"
                                    @update:value="onTotalOrTaxChange(item.id, item)"
                                    size="small"
                                    style="width: 90px"
                                />
                            </div>
                            <div v-else>
                                {{ formatCurrency(item.total) }}
                                <div v-if="parseFloat(item.subtotal) > parseFloat(item.total)" class="line-discount">
                                    {{ formatCurrency(item.subtotal - item.total) }} discount
                                </div>
                            </div>
                            <div v-if="item.total_refunded > 0" class="refunded-amount">
                                ↩ -{{ formatCurrency(item.total_refunded) }}
                            </div>
                        </td>

                        <!-- Tax -->
                        <td>
                            <div v-if="refundMode">
                                <div>{{ formatCurrency(item.total_tax) }}</div>
                                <n-input-number
                                    v-model:value="getRefundItem(item.id).tax"
                                    :min="0"
                                    :max="item.total_tax"
                                    @update:value="onTotalOrTaxChange(item.id, item)"
                                    size="small"
                                    style="width: 70px"
                                />
                            </div>
                            <div v-else>
                                {{ formatCurrency(+item.total_tax) }}
                            </div>
                            <div v-if="item.refunded_tax > 0" class="refunded-amount">
                                ↩ -{{ formatCurrency(item.refunded_tax) }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="refundMode"
                class="refund-actions"
                style="margin-top: 1rem; display: flex; gap: 12px; justify-content: flex-end"
            >
                <n-button tertiary size="small" type="warning" @click="refundAllItems">Refund All</n-button>
                <n-button tertiary size="small" type="default" @click="resetRefundItems">Reset</n-button>
            </div>

            <div class="totals-section" style="margin-top: 1rem">
                <p><strong>Items Subtotal:</strong> {{ formatCurrency(subtotal) }}</p>
            </div>
            <hr class="totals-divider" />

            <!-- ─────────── Fees ─────────── -->
            <div v-if="order.fee_lines?.length" class="totals-section">
                <h4>Fees</h4>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Fee</th>
                            <th>Total</th>
                            <th>Tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fee in order.fee_lines" :key="fee.id">
                            <!-- Fee Name -->
                            <td>{{ fee.name }}</td>

                            <!-- Fee Total -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(fee.total) }}
                                    <n-input-number
                                        v-model:value="getRefundFee(fee.id).total"
                                        :min="0"
                                        :max="fee.total"
                                        size="small"
                                        style="width: 90px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(fee.total) }}
                                </div>
                                <div v-if="+fee.total_refunded !== 0" class="refunded-amount">
                                    ↩ -{{ formatCurrency(Math.abs(fee.total_refunded)) }}
                                </div>
                            </td>

                            <!-- Fee Tax -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(fee.total_tax) }}
                                    <n-input-number
                                        v-model:value="getRefundFee(fee.id).tax"
                                        :min="0"
                                        :max="fee.total_tax"
                                        size="small"
                                        style="width: 70px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(+fee.total_tax) }}
                                </div>
                                <div v-if="+fee.refunded_tax !== 0" class="refunded-amount">
                                    ↩ -{{ formatCurrency(Math.abs(fee.refunded_tax)) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ─────────── Shipping ─────────── -->
            <div class="totals-section">
                <h4>Shipping</h4>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Total</th>
                            <th>Tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ship in order.shipping_lines" :key="ship.id">
                            <!-- Method -->
                            <td>{{ ship.method_title }}</td>

                            <!-- Shipping Total -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(+ship.total) }}
                                    <n-input-number
                                        v-model:value="getRefundShipping(ship.id).total"
                                        :min="0"
                                        :max="+ship.total"
                                        size="small"
                                        style="width: 90px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(+ship.total) }}
                                </div>
                                <div v-if="+ship.total_refunded !== 0" class="refunded-amount">
                                    ↩ -{{ formatCurrency(Math.abs(ship.total_refunded)) }}
                                </div>
                            </td>

                            <!-- Shipping Tax -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(+ship.total_tax) }}
                                    <n-input-number
                                        v-model:value="getRefundShipping(ship.id).tax"
                                        :min="0"
                                        :max="+ship.total_tax"
                                        size="small"
                                        style="width: 70px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(+ship.total_tax) }}
                                </div>
                                <div v-if="+ship.refunded_tax !== 0" class="refunded-amount">
                                    ↩ -{{ formatCurrency(Math.abs(ship.refunded_tax)) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="refundMode"
                class="refund-actions"
                style="margin-top: 1rem; display: flex; gap: 12px; justify-content: flex-end"
            >
                <n-button tertiary size="small" type="warning" @click="refundAllFeesAndShipping">
                    Refund All Fees & Shipping
                </n-button>
                <n-button tertiary size="small" type="default" @click="resetFeesAndShipping">Reset</n-button>
            </div>

            <!-- ─────────── Taxes / Discounts ─────────── -->
            <div v-if="order.tax_lines?.length" class="totals-section">
                <p><strong>Taxes total:</strong> {{ formatCurrency(totalTaxAmount) }}</p>
            </div>

            <div v-if="order.coupon_lines?.length" class="totals-section">
                <p><strong>Discounts:</strong></p>
                <ul class="breakdown-list coupon-list">
                    <li v-for="c in order.coupon_lines" :key="c.id">
                        <span class="coupon-code">{{ c.code }}</span> — {{ formatCurrency(c.discount) }}
                    </li>
                </ul>
            </div>

            <!-- ─────────── Final totals / buttons ─────────── -->
            <hr class="totals-divider" />
            <div class="totals-section final-totals">
                <div v-if="totalRefunded > 0" class="refunded-row">
                    <p><strong>Refunded:</strong> ↩ -{{ formatCurrency(totalRefunded) }}</p>
                </div>
                <p><strong>Order Total:</strong> {{ formatCurrency(order.total) }}</p>
                <p><strong>Paid:</strong> {{ formatCurrency(order.total) }}</p>
            </div>

            <hr />

            <n-button size="small" type="warning" v-if="!refundMode" @click="enterRefundMode">Refund</n-button>
            <n-space vertical size="small" style="margin-top: 1rem" v-if="refundMode">
                <div><strong>Total available to refund:</strong> {{ formatCurrency(totalAvailableToRefund) }}</div>
                <div><strong>Refund amount:</strong> {{ formatCurrency(refundTotalAmount) }}</div>
                <n-checkbox v-model:checked="refundViaBraintree"> Refund via Braintree </n-checkbox>
                <n-space>
                    <n-button
                        type="primary"
                        :disabled="!hasRefund || isRefunding"
                        :loading="isRefunding"
                        @click="confirmRefund"
                    >
                        Proceed Refund
                    </n-button>
                    <n-button ghost @click="cancelRefund">Cancel</n-button>
                </n-space>
            </n-space>
        </div>
    </div>
</template>

<script setup>
/* Minimal comments, maximum clarity */
import { ref, computed, watch } from "vue";
import { useMessage } from "naive-ui";
import { formatCurrency, setCurrency } from "@/utils/utils";
import { request } from "@/utils/api";

const props = defineProps({
    order: {
        type: Object,
        default: () => ({}),
    },
    orderId: { type: [String, Number], required: true },
    loading: Boolean,
});
const emit = defineEmits(["updateOrder"]);

const message = useMessage();

/* ── refund-specific state ── */
const refundMode = ref(false);
const showConfirmModal = ref(false);
const isRefunding = ref(false);

const refundItems = ref([]);
const refundFees = ref([]);
const refundShipping = ref([]);

const pendingRefundItems = ref([]);
const pendingRefundFees = ref([]);
const pendingRefundShipping = ref([]);
const refundViaBraintree = ref(true);

/* ── helpers ── */
const order = computed(() => props.order || {});

function getRefundItem(id) {
    return refundItems.value.find((i) => i.id === id);
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

/* ── currency helper ── */
watch(
    () => order.value.currency,
    (c) => c && setCurrency(c),
    { immediate: true }
);

/* ── computed totals ── */
const subtotal = computed(() => order.value.line_items?.reduce((t, i) => t + +i.subtotal || 0, 0).toFixed(2) || "—");
const totalRefunded = computed(() => order.value.refunds?.reduce((s, r) => s + Math.abs(+r.total || 0), 0) || 0);
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
    const total = parseFloat(order.value.total || 0);
    return Math.max(0, total - totalRefunded.value);
});

const totalTaxAmount = computed(() =>
    order.value.tax_lines?.reduce((sum, tax) => sum + parseFloat(tax.tax_total || 0), 0).toFixed(2)
);

/* ── refund mode management ── */
function enterRefundMode() {
    refundMode.value = true;
    refundItems.value = order.value.line_items.map((i) => ({
        id: i.id,
        quantity: 0,
        total: 0,
        tax: 0,
        transaction_id: getTransactionId(i),
    }));
    refundFees.value = order.value.fee_lines.map((f) => ({ id: f.id, total: 0, tax: 0 }));
    refundShipping.value = order.value.shipping_lines.map((s) => ({ id: s.id, total: 0, tax: 0 }));
}
function cancelRefund() {
    refundMode.value = false;
    refundItems.value = [];
    refundFees.value = [];
    refundShipping.value = [];
}

function onQuantityChange(id, item) {
    const r = getRefundItem(id);
    const q = +r.quantity || 0;
    const unitTotal = +item.total / +item.quantity || 1;
    const unitTax = +item.total_tax / +item.quantity || 1;
    r.total = +(unitTotal * q).toFixed(2);
    r.tax = +(unitTax * q).toFixed(2);
}
function onTotalOrTaxChange(id, item) {
    const r = getRefundItem(id);
    const q = +r.quantity || 0;
    const unitTotal = +item.total / +item.quantity || 1;
    const unitTax = +item.total_tax / +item.quantity || 1;
    const expTotal = +(unitTotal * q).toFixed(2);
    const expTax = +(unitTax * q).toFixed(2);
    if (Math.abs(r.total - expTotal) > 0.01 || Math.abs(r.tax - expTax) > 0.01) r.quantity = 0;
}

/* ── quick helpers ── */
function refundAllItems() {
    refundItems.value = order.value.line_items.map((i) => ({
        id: i.id,
        quantity: i.quantity,
        total: +i.total,
        tax: +i.total_tax,
    }));
}
function refundAllFeesAndShipping() {
    refundFees.value = order.value.fee_lines.map((f) => ({ id: f.id, total: +f.total, tax: +f.total_tax }));
    refundShipping.value = order.value.shipping_lines.map((s) => ({ id: s.id, total: +s.total, tax: +s.total_tax }));
}
function resetRefundItems() {
    refundItems.value = refundItems.value.map((i) => ({ ...i, quantity: 0, total: 0, tax: 0 }));
}
function resetFeesAndShipping() {
    refundFees.value = refundFees.value.map((f) => ({ ...f, total: 0, tax: 0 }));
    refundShipping.value = refundShipping.value.map((s) => ({ ...s, total: 0, tax: 0 }));
}

/* ── user confirmation ── */
function confirmRefund() {
    pendingRefundItems.value = refundItems.value.filter((i) => +i.quantity || +i.total || +i.tax);
    pendingRefundFees.value = refundFees.value.filter((f) => +f.total || +f.tax);
    pendingRefundShipping.value = refundShipping.value.filter((s) => +s.total || +s.tax);

    if (!pendingRefundItems.value.length && !pendingRefundFees.value.length && !pendingRefundShipping.value.length) {
        message.warning("No items, fees, or shipping selected for refund");
        return;
    }
    showConfirmModal.value = true;
}

/* ── refund API call ── */
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
            refund_via_braintree: refundViaBraintree.value ? 1 : 0,
        };

        const res = await request({
            url: `/orders/${props.orderId}/refund`,
            method: "POST",
            body: payload,
            raw: true, // 👈 because we want to manually parse response text
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
        console.error(e);
    } finally {
        isRefunding.value = false;
    }
}
</script>
