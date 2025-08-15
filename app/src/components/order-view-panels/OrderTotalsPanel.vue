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

        <!-- Confirm edit modal -->
        <n-modal v-model:show="showEditConfirmModal" preset="dialog" type="info" title="Confirm Edit">
            <template #default>
                Are you sure you want to save these changes to the order?<br />This action will be logged and cannot be
                undone.
            </template>
            <template #action>
                <n-button ghost @click="showEditConfirmModal = false">Cancel</n-button>
                <n-button type="primary" :loading="isEditing" @click="confirmEdit">Proceed</n-button>
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
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.line_items" :key="item.id" v-show="!(editMode && isItemRemoved(item.id))">
                        <!-- Product Info -->
                        <td>
                            <div class="product-cell">
                                <!-- Remove button -->
                                <n-tooltip v-if="editMode" trigger="hover">
                                    <template #trigger>
                                        <n-icon class="remove-icon" size="18" @click="removeItem(item)">
                                            <CloseCircleOutlined />
                                        </n-icon>
                                    </template>
                                    Remove product
                                </n-tooltip>
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

                        <!-- Quantity -->
                        <td>
                            <div v-if="refundMode">
                                <div>× {{ item.quantity }}</div>
                                <n-input-number
                                    v-model:value="getRefundItem(item.id).quantity"
                                    :min="0"
                                    :max="item.quantity"
                                    @update:value="onRefundQuantityChange(item.id, item)"
                                    size="small"
                                    style="max-width: 80px"
                                />
                            </div>
                            <div v-else-if="editMode">
                                <n-input-number
                                    :value="getEditItem(item.id).quantity"
                                    @update:value="(val) => onEditItemChange(item.id, 'quantity', val)"
                                    :min="0"
                                    size="small"
                                    style="max-width: 80px"
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
                                    style="max-width: 100px"
                                />
                            </div>
                            <div v-else-if="editMode">
                                <n-input-number
                                    :value="getEditItem(item.id).total"
                                    @update:value="(val) => onEditItemChange(item.id, 'total', val)"
                                    :min="0"
                                    size="small"
                                    style="max-width: 100px"
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
                                    style="max-width: 100px"
                                />
                            </div>
                            <div v-else-if="editMode">
                                <n-input-number
                                    :value="getEditItem(item.id).tax"
                                    @update:value="(val) => onEditItemChange(item.id, 'tax', val)"
                                    :min="0"
                                    size="small"
                                    style="max-width: 100px"
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

                    <!-- New products being added (edit mode only) -->
                    <tr v-if="editMode" v-for="item in editItems.filter((i) => i.isNew)" :key="'new-' + item.id">
                        <!-- Product dropdown -->
                        <td>
                            <div style="position: relative">
                                <n-tooltip v-if="editMode" trigger="hover">
                                    <template #trigger>
                                        <n-icon class="remove-icon" size="18" @click="removeItem(item)">
                                            <CloseCircleOutlined />
                                        </n-icon>
                                    </template>
                                    Remove product
                                </n-tooltip>
                                <n-select
                                    v-model:value="item.product_id"
                                    :options="productOptions"
                                    :loading="productLoading"
                                    placeholder="Select product"
                                    :render-label="
                                        (option) => (option.renderLabel ? option.renderLabel() : option.label)
                                    "
                                    @update:value="(val) => onProductSelected(item.id, val)"
                                    style="min-width: 200px"
                                />
                            </div>
                        </td>
                        <!-- Quantity -->
                        <td>
                            <n-input-number
                                :value="item.quantity"
                                @update:value="(val) => onEditItemChange(item.id, 'quantity', val)"
                                :min="1"
                                size="small"
                                style="max-width: 80px"
                            />
                        </td>
                        <!-- Total -->
                        <td>
                            <n-input-number v-model:value="item.total" :min="0" size="small" style="max-width: 100px" />
                        </td>
                        <!-- Tax -->
                        <td>
                            <n-input-number v-model:value="item.tax" :min="0" size="small" style="max-width: 100px" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <n-button
                v-if="editMode"
                size="small"
                @click="onAddProduct"
                :loading="productLoading"
                style="margin-top: 8px"
            >
                Add Product
            </n-button>

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
                        <tr v-for="fee in order.fee_lines" :key="fee.id" v-show="!(editMode && isFeeRemoved(fee.id))">
                            <!-- Fee Name -->
                            <td>
                                <div style="position: relative">
                                    <template v-if="editMode">
                                        <n-input
                                            :value="getEditFee(fee.id).name"
                                            @update:value="(val) => onEditFeeChange(fee.id, 'name', val)"
                                            size="small"
                                            style="max-width: 160px"
                                        />
                                    </template>
                                    <template v-else>
                                        {{ fee.name }}
                                    </template>
                                    <n-tooltip v-if="editMode" trigger="hover">
                                        <template #trigger>
                                            <n-icon class="remove-icon qwerty" size="18" @click="removeFee(fee)">
                                                <CloseCircleOutlined />
                                            </n-icon>
                                        </template>
                                        Remove fee
                                    </n-tooltip>
                                </div>
                            </td>

                            <!-- Fee Total -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(fee.total) }}
                                    <n-input-number
                                        v-model:value="getRefundFee(fee.id).total"
                                        :min="0"
                                        :max="fee.total"
                                        size="small"
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else-if="editMode">
                                    <n-input-number
                                        :value="getEditFee(fee.id).total"
                                        @update:value="(val) => onEditFeeChange(fee.id, 'total', val)"
                                        :min="0"
                                        size="small"
                                        style="max-width: 100px"
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
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else-if="editMode">
                                    <n-input-number
                                        :value="getEditFee(fee.id).tax"
                                        @update:value="(val) => onEditFeeChange(fee.id, 'tax', val)"
                                        :min="0"
                                        size="small"
                                        style="max-width: 100px"
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

                        <tr v-if="editMode" v-for="fee in editFees.filter((f) => f.isNew)" :key="'new-fee-' + fee.id">
                            <td>
                                <div style="position: relative">
                                    <n-input
                                        v-model:value="fee.name"
                                        placeholder="Fee name"
                                        size="small"
                                        style="max-width: 160px"
                                    />
                                    <n-tooltip v-if="editMode" trigger="hover">
                                        <template #trigger>
                                            <n-icon class="remove-icon" size="18" @click="removeFee(fee)">
                                                <CloseCircleOutlined />
                                            </n-icon>
                                        </template>
                                        Remove fee
                                    </n-tooltip>
                                </div>
                            </td>
                            <td>
                                <n-input-number
                                    v-model:value="fee.total"
                                    :min="0"
                                    size="small"
                                    style="max-width: 100px"
                                />
                            </td>
                            <td>
                                <n-input-number
                                    v-model:value="fee.tax"
                                    :min="0"
                                    size="small"
                                    style="max-width: 100px"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <n-button v-if="editMode" size="small" @click="onAddFee" style="margin-top: 8px"> Add Fee </n-button>
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
                            <td>
                                <template v-if="editMode">
                                    <n-select
                                        v-model:value="getEditShipping(ship.id).method_id"
                                        :options="
                                            shippingMethodOptions.map((opt) => ({
                                                label: `${opt.label} - $${opt.cost}`,
                                                value: opt.id,
                                            }))
                                        "
                                        placeholder="Select method"
                                        @update:value="
                                            (val) => {
                                                const selected = shippingMethodOptions.find((opt) => opt.id === val);
                                                onEditShippingChange(ship.id, 'method_title', selected?.label || val);
                                                onEditShippingChange(ship.id, 'method_id', val);
                                                onEditShippingChange(ship.id, 'total', selected?.cost || 0);
                                            }
                                        "
                                        size="small"
                                        style="max-width: 200px"
                                    />
                                </template>
                                <template v-else>
                                    {{ ship.method_title }}
                                </template>
                            </td>

                            <!-- Shipping Total -->
                            <td>
                                <div v-if="refundMode">
                                    {{ formatCurrency(+ship.total) }}
                                    <n-input-number
                                        v-model:value="getRefundShipping(ship.id).total"
                                        :min="0"
                                        :max="+ship.total"
                                        size="small"
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else-if="editMode">
                                    <n-input-number
                                        :value="getEditShipping(ship.id).total"
                                        @update:value="(val) => onEditShippingChange(ship.id, 'total', val)"
                                        :min="0"
                                        size="small"
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(+ship.total) }}
                                </div>
                                <div
                                    v-if="ship.total_refunded != null && +ship.total_refunded !== 0"
                                    class="refunded-amount"
                                >
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
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else-if="editMode">
                                    <n-input-number
                                        :value="+getEditShipping(ship.id).tax || 0"
                                        @update:value="(val) => onEditShippingChange(ship.id, 'tax', val)"
                                        :min="0"
                                        size="small"
                                        style="max-width: 100px"
                                    />
                                </div>
                                <div v-else>
                                    {{ formatCurrency(+ship.total_tax || 0) }}
                                </div>
                                <div
                                    v-if="ship.refunded_tax != null && +ship.refunded_tax !== 0"
                                    class="refunded-amount"
                                >
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

                    <ul class="refunds-list" style="margin-top: 0.5rem; list-style: disc; padding-left: 1.5rem">
                        <li v-for="refund in order.refunds" :key="refund.id">
                            {{ formatCurrency(refund.total) }} —
                            {{ formatOrderDate(refund.date) }}
                            <span v-if="refund.reason">— {{ refund.reason }}</span>
                        </li>
                    </ul>
                </div>
                <p><strong>Order Total:</strong> {{ formatCurrency(order.total) }}</p>
                <p><strong>Paid:</strong> {{ formatCurrency(order.total) }}</p>
            </div>

            <hr />

            <n-space size="small" style="margin-top: 1rem" v-if="!refundMode && !editMode">
                <n-button
                    v-if="$can('refund_orders') && props.sourceType === 'order'"
                    size="medium"
                    type="default"
                    @click="enterRefundMode"
                    >Refund</n-button
                >
                <n-button
                    v-if="
                        (props.sourceType !== 'replacement' && $can('edit_orders_items')) ||
                        (props.sourceType === 'replacement' && $can('edit_replacement_orders'))
                    "
                    size="medium"
                    type="default"
                    @click="enterEditMode"
                >
                    Edit
                </n-button>
            </n-space>

            <n-space vertical size="medium" style="margin-top: 1rem" v-if="refundMode && $can('refund_orders')">
                <div style="display: flex; gap: 12px; align-items: center">
                    <div style="display: flex; gap: 12px; align-items: center">
                        <div><strong>Refund %</strong></div>
                        <n-input-number
                            v-model:value="refundPercent"
                            :min="1"
                            :max="100"
                            size="small"
                            style="width: 100px"
                            placeholder="Refund %"
                            @update:value="onRefundPercentInput"
                        />
                    </div>
                </div>
                <div><strong>Total available to refund:</strong> {{ formatCurrency(totalAvailableToRefund) }}</div>
                <div><strong>Refund amount:</strong> {{ formatCurrency(refundTotalAmount) }}</div>

                <n-checkbox v-if="order.payment_method?.includes('braintree')" v-model:checked="refundViaBraintree">
                    Refund via Braintree
                </n-checkbox>

                <!-- 📝 Refund Reason -->
                <div>
                    <n-input
                        v-model:value="refundReason"
                        type="textarea"
                        placeholder="Enter reason for refund (optional)"
                        :autosize="{ minRows: 2, maxRows: 4 }"
                    />
                </div>

                <n-space style="margin-top: 1rem">
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

            <n-space
                vertical
                size="medium"
                style="margin-top: 1rem"
                v-if="
                    editMode &&
                    ((props.sourceType !== 'replacement' && $can('edit_orders_items')) ||
                        (props.sourceType === 'replacement' && $can('edit_replacement_orders')))
                "
            >
                <n-checkbox
                    v-if="props.sourceType === 'order'"
                    v-model:checked="autoTaxCalc"
                    style="margin-bottom: 1em"
                >
                    Calculate Taxes Automatically
                </n-checkbox>
                <n-space>
                    <n-button type="primary" :disabled="false" @click="proceedEdit">Proceed Edit</n-button>
                    <n-button ghost @click="cancelEdit">Cancel</n-button>
                </n-space>
            </n-space>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { useMessage } from "naive-ui";
import { formatCurrency, setCurrency } from "@/utils/utils";
import { useRefund } from "@/composables/useRefund";
import { useEdit } from "@/composables/useEdit";
import { CloseCircleOutlined } from "@vicons/antd";
import { formatOrderDate } from "@/utils/utils";
const props = defineProps({
    order: {
        type: Object,
        default: () => ({}),
    },
    orderId: { type: [String, Number], required: true },
    loading: Boolean,
    sourceType: {
        type: String,
        default: "order",
    },
});
const emit = defineEmits(["updateOrder"]);
const message = useMessage();

const order = computed(() => props.order || {});

const shippingMethodOptions = [
    { id: "free_shipping:3", label: "Free Shipping", cost: 0 },
    { id: "flat_rate:1", label: "Expedited Shipping", cost: 15 },
    { id: "flat_rate:10", label: "Overnight Shipping", cost: 25 },
    { id: "flat_rate:4", label: "Outside of US Shipping", cost: 9 },
];

const {
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
    refundReason,

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
} = useRefund({
    order,
    orderId: props.orderId,
    emit,
    message,
    formatCurrency,
});
const {
    // State
    editMode, // ref: is edit mode active
    editItems, // ref: array of editing line items (old & new)
    editFees, // ref: array of editing fees
    editShipping, // ref: array of editing shipping lines
    isEditing, // ref: is a save in progress
    showEditConfirmModal, // ref: show confirmation dialog

    // Removal tracking
    removedItems, // ref: array of removed item IDs

    // Main actions
    enterEditMode, // function: enter edit mode (populate editItems etc)
    cancelEdit, // function: cancel edit mode, reset
    proceedEdit, // function: show confirmation modal
    confirmEdit, // function: send edit payload to backend

    // Editing helpers
    getEditItem, // function: get editItem by id
    getEditFee, // function: get editFee by id
    getEditShipping, // function: get editShipping by id
    onEditItemChange, // function: update edit item field
    onEditFeeChange, // function: update fee field
    onEditShippingChange, // function: update shipping field

    // Remove logic
    removeItem, // function: mark item as removed or remove new
    isItemRemoved, // function: returns true if item is removed in edit mode

    // Add product logic
    productOptions, // ref: product select options
    productLoading, // ref: loading state for options
    onAddProduct, // function: add new product row
    onProductSelected, // function: handle select in new product row
    loadProductOptions, // function: trigger product options load

    onAddFee, // function: add new fee row
    removeFee, // function: remove fee row
    isFeeRemoved, // function: check if fee is removed in edit mode
    autoTaxCalc,
} = useEdit({ order, orderId: props.orderId, message, emit }, props.sourceType);
</script>
