<!-- SubscriptionTotalsPanel.vue -->
<template>
    <div class="panel totals-panel">
        <n-modal v-model:show="showEditConfirmModal" preset="dialog" type="info" title="Confirm Edit">
            <template #default>
                Are you sure you want to save these changes to the subscription?<br />This action will be logged and
                cannot be undone.
            </template>
            <template #action>
                <n-button ghost @click="showEditConfirmModal = false">Cancel</n-button>
                <n-button type="primary" :loading="isEditing" @click="confirmEdit">Proceed</n-button>
            </template>
        </n-modal>

        <n-modal v-model:show="showProcessRenewalModal" preset="dialog" type="warning" title="Process Renewal">
            <template #default>
                Are you sure you want to process a renewal for this subscription?<br />
                This will attempt to generate a renewal order and charge the customer.
            </template>
            <template #action>
                <n-button ghost @click="showProcessRenewalModal = false">Cancel</n-button>
                <n-button type="primary" :loading="processingRenewal" @click="processRenewal">Proceed</n-button>
            </template>
        </n-modal>

        <h3>Subscription Totals</h3>
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
                    <tr
                        v-for="item in subscription.line_items"
                        :key="item.id"
                        v-show="!(editMode && isItemRemoved(item.id))"
                    >
                        <td>
                            <div class="product-cell">
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
                                    </div>
                                    <small v-if="item.sku">SKU: {{ item.sku }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div v-if="editMode">
                                <n-input-number
                                    :value="getEditItem(item.id).quantity"
                                    @update:value="(val) => onEditItemChange(item.id, 'quantity', val)"
                                    :min="1"
                                    size="small"
                                    style="max-width: 80px"
                                />
                            </div>
                            <div v-else>
                                {{ item.quantity }}
                            </div>
                        </td>
                        <td>
                            <div v-if="editMode">
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
                        </td>
                        <td>
                            <div v-if="editMode">
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
                        </td>
                    </tr>
                    <!-- New products being added (edit mode only) -->
                    <tr v-if="editMode" v-for="item in editItems.filter((i) => i.isNew)" :key="'new-' + item.id">
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
                        <td>
                            <n-input-number
                                :value="item.quantity"
                                @update:value="(val) => onEditItemChange(item.id, 'quantity', val)"
                                :min="1"
                                size="small"
                                style="max-width: 80px"
                            />
                        </td>
                        <td>
                            <n-input-number v-model:value="item.total" :min="0" size="small" style="max-width: 100px" />
                        </td>
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

            <div class="totals-section" style="margin-top: 1rem">
                <p><strong>Items Subtotal:</strong> {{ formatCurrency(subtotal) }}</p>
            </div>
            <hr class="totals-divider" />

            <!-- ─────────── Taxes / Discounts ─────────── -->

            <p><strong>Taxes total:</strong> {{ formatCurrency(totalTaxAmount) }}</p>

            <div v-if="subscription.coupon_lines?.length" class="totals-section">
                <p><strong>Discounts:</strong></p>
                <ul class="breakdown-list coupon-list">
                    <li v-for="c in subscription.coupon_lines" :key="c.id">
                        <span class="coupon-code">{{ c.code }}</span> — {{ formatCurrency(c.discount) }}
                    </li>
                </ul>
            </div>
            <!-- ─────────── Final totals / buttons ─────────── -->
            <hr class="totals-divider" />
            <div class="totals-section final-totals">
                <p><strong>Subscription Total:</strong> {{ formatCurrency(subscription.total) }}</p>
            </div>
            <hr />
            <n-space vertical size="small" style="margin-top: 1rem" v-if="editMode">
                <n-checkbox v-model:checked="autoTaxCalc" style="margin-bottom: 1em">
                    Calculate Taxes Automatically
                </n-checkbox>
                <n-space>
                    <n-button type="primary" :disabled="false" @click="proceedEdit"> Proceed Edit </n-button>
                    <n-button ghost @click="cancelEdit">Cancel</n-button>
                </n-space>
            </n-space>
            <n-space size="medium" style="margin-top: 1rem" v-if="!editMode && $can('edit_subscriptions')">
                <n-button size="medium" type="default" @click="enterEditMode"> Edit </n-button>
            </n-space>
            <n-space size="small" style="margin-top: 1rem" v-if="!editMode && $can('edit_subscriptions')">
                <n-button size="medium" type="default" @click="showProcessRenewalModal = true">
                    Process Renewal
                </n-button>
            </n-space>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { useMessage } from "naive-ui";
import { formatCurrency } from "@/utils/utils";
import { useSubscriptionEdit } from "@/composables/useSubscriptionEdit";
import { CloseCircleOutlined } from "@vicons/antd";

const props = defineProps({
    subscription: {
        type: Object,
        default: () => ({}),
    },
    subscriptionId: { type: [String, Number], required: true },
    loading: Boolean,
});
const emit = defineEmits(["update-subscription"]);
const message = useMessage();

const subscription = computed(() => props.subscription || {});

const {
    editMode,
    editItems,
    isEditing,
    showEditConfirmModal,
    enterEditMode,
    cancelEdit,
    proceedEdit,
    confirmEdit,
    getEditItem,
    onEditItemChange,
    removeItem,
    isItemRemoved,
    productOptions,
    productLoading,
    onAddProduct,
    onProductSelected,
    subtotal,
    totalTaxAmount,
    autoTaxCalc,
    showProcessRenewalModal,
    processingRenewal,
    processRenewal,

    // more helpers if needed...
} = useSubscriptionEdit({
    subscription,
    subscriptionId: props.subscriptionId,
    message,
    emit,
});
</script>
