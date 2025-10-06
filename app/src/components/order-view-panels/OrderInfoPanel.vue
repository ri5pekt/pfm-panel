<!-- OrderInfoPanel.vue -->
<template>
    <div class="panel general-info-panel">
        <h3>General Info</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <div class="order-info">
                <div class="cols">
                    <div class="col">
                        <h4>Order info</h4>
                        <p><strong>ID:</strong> {{ order.id }}</p>

                        <p>
                            <strong>Status:</strong>
                            <template v-if="editingOrderInfo">
                                <n-select
                                    v-model:value="editableOrderInfo.status"
                                    :options="statusOptions"
                                    placeholder="Select status"
                                    style="width: 200px"
                                />
                            </template>
                            <template v-else> &nbsp;{{ order.status }} </template>
                        </p>

                        <p v-if="props.sourceType === 'replacement'">
                            <strong>Reason:</strong>
                            <template v-if="editingOrderInfo">
                                <n-select
                                    v-model:value="editableOrderInfo.replacementReason"
                                    :options="replacementReasonOptions"
                                    placeholder="Select reason"
                                    style="width: 250px"
                                />
                            </template>
                            <template v-else> &nbsp;{{ props.getMeta?.("replacement_reason") || "—" }} </template>
                        </p>

                        <p><strong>Date Created:</strong> {{ formattedCreatedDate }}</p>

                        <p>
                            <strong>Order Total: </strong>
                            <span v-if="order.total !== undefined && order.total !== null">
                                {{ order.total }} <span v-if="order.currency">{{ order.currency }}</span>
                            </span>
                            <span v-else>—</span>
                        </p>

                        <p v-if="!props.isArchived && editableOrderInfo.newOrReturning">
                            <strong>New/Returning:</strong>
                            <template v-if="editingOrderInfo">
                                <n-select
                                    v-model:value="editableOrderInfo.newOrReturning"
                                    :options="newOrReturningOptions"
                                    placeholder="Select type"
                                    style="width: 200px"
                                />
                            </template>
                            <template v-else> &nbsp;{{ newOrReturning }} </template>
                        </p>

                        <p v-if="!props.isArchived">
                            <strong>Payment Method:</strong> {{ order.payment_method_title }}
                        </p>

                        <p v-if="!props.isArchived && order.transaction_id">
                            <strong>Transaction ID: </strong>
                            <template v-if="order.payment_method?.includes('braintree')">
                                <a
                                    :href="`https://braintreegateway.com/merchants/y4mkhnwqj95zycvr/transactions/${order.transaction_id}`"
                                    target="_blank"
                                    style="color: #4098fc; text-decoration: underline"
                                >
                                    {{ order.transaction_id }}
                                </a>
                            </template>
                            <template v-else-if="order.payment_method?.includes('bluesnap')">
                                <a
                                    :href="`https://cp.bluesnap.com/jsp/order_locator_info.jsp?invoiceId=${order.transaction_id}`"
                                    target="_blank"
                                    style="color: #4098fc; text-decoration: underline"
                                >
                                    {{ order.transaction_id }}
                                </a>
                            </template>
                            <template v-else>
                                {{ order.transaction_id }}
                            </template>
                        </p>

                        <!-- Hotjar recordings (if any) -->
                        <p v-if="!props.isArchived && hotjarLinks.length">
                            <strong>Hotjar:</strong>
                            <span style="margin-left: 6px">
                                <template v-for="(url, i) in hotjarLinks" :key="url">
                                    <a :href="url" target="_blank" rel="noopener" style="text-decoration: underline">
                                        Recording {{ i + 1 }} </a
                                    ><span v-if="i < hotjarLinks.length - 1">, </span>
                                </template>
                            </span>
                        </p>

                        <p v-if="!props.isArchived">
                            <strong>Chargeback Alert:</strong>
                            <template v-if="editingOrderInfo">
                                <n-select
                                    v-model:value="editableOrderInfo.chargebackAlert"
                                    :options="chargebackAlertOptions"
                                    placeholder="Select alert"
                                    style="width: 200px"
                                />
                            </template>
                            <template v-else>
                                <span
                                    :style="{
                                        color:
                                            props.getMeta?.('chargeback_alert_received') === 'yes' ? 'red' : 'inherit',
                                    }"
                                >
                                    &nbsp;{{ props.getMeta?.("chargeback_alert_received") || "—" }}
                                </span>
                            </template>
                        </p>
                    </div>

                    <!-- Braintree column -->
                    <div v-if="shouldShowBraintreeColumn" class="col">
                        <template v-if="loadingBraintree">
                            <p style="display: flex; align-items: center; gap: 8px">
                                <n-spin size="small" />
                                Fetching Braintree info…
                            </p>
                        </template>

                        <template v-else>
                            <h4>Braintree</h4>

                            <template v-if="btTransaction">
                                <div title="Transaction" type="default" :bordered="false">
                                    <p><strong>Status:</strong> {{ btTransaction.status || "—" }}</p>

                                    <p>
                                        <strong>AVS: </strong>
                                        <span>street: {{ btTransaction.avs?.street?.text || "—" }}</span
                                        >,
                                        <span>postal: {{ btTransaction.avs?.postal?.text || "—" }}</span>
                                    </p>

                                    <p><strong>CVV:</strong> {{ btTransaction.cvv_response?.text || "—" }}</p>

                                    <div style="display: flex; align-items: center; gap: 8px">
                                        <strong>Risk:</strong>
                                        <template v-if="btTransaction.risk">
                                            <n-tag :type="riskTagType">
                                                {{ btTransaction.risk.decision || "—" }}
                                            </n-tag>
                                            <span
                                                v-if="
                                                    btTransaction.risk.score !== null &&
                                                    btTransaction.risk.score !== undefined
                                                "
                                            >
                                                &middot; score: {{ btTransaction.risk.score }}
                                            </span>
                                            <span v-if="btTransaction.risk.id">
                                                &middot; id: {{ btTransaction.risk.id }}
                                            </span>
                                        </template>
                                        <template v-else> — </template>
                                    </div>
                                </div>
                            </template>

                            <div style="margin-top: 12px">
                                <h4 style="color: red; margin-bottom: 4px">Disputes</h4>
                                <template v-if="btDisputes.length">
                                    <div
                                        v-for="(d, i) in btDisputes"
                                        :key="d.id || i"
                                        class="dispute-card"
                                        style="
                                            padding: 8px 10px;
                                            border: 1px solid #eee;
                                            border-radius: 8px;
                                            margin-bottom: 8px;
                                        "
                                    >
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px">
                                            <n-tag :type="disputeTagType(d.status)">{{ d.status || "—" }}</n-tag>
                                            <span v-if="d.id" style="opacity: 0.7">#{{ d.id }}</span>
                                        </div>
                                        <p><strong>Reason:</strong> {{ d.reason || "—" }}</p>
                                        <p>
                                            <strong>Amount:</strong>
                                            {{ d.amount ?? "—" }}
                                            <span v-if="d.currency">{{ d.currency }}</span>
                                        </p>
                                        <p><strong>Received:</strong> {{ d.received_date || "—" }}</p>
                                        <p><strong>Reply By:</strong> {{ d.reply_by || "—" }}</p>
                                    </div>
                                </template>
                                <template v-else>
                                    <p>— No disputes for this transaction</p>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="
                        (props.sourceType !== 'replacement' && $can('edit_orders_main_info')) ||
                        (props.sourceType === 'replacement' && $can('edit_replacement_orders'))
                    "
                    style="margin-top: 1rem"
                >
                    <template v-if="editingOrderInfo">
                        <n-button size="medium" type="primary" :loading="savingOrderInfo" @click="saveOrderInfo"
                            >Save</n-button
                        >
                        <n-button
                            size="medium"
                            ghost
                            style="margin-left: 0.5rem"
                            @click="cancelEditOrderInfo"
                            :disabled="savingOrderInfo"
                        >
                            Cancel
                        </n-button>
                    </template>
                    <template v-else-if="!props.isArchived">
                        <n-button size="medium" type="default" @click="enterEditOrderInfo">Edit</n-button>
                    </template>
                </div>

                <!-- Delete button for replacement orders -->
                <div
                    v-if="props.sourceType === 'replacement' && $can('edit_replacement_orders')"
                    style="margin-top: 1rem"
                >
                    <n-button type="error" size="small" @click="showDeleteConfirm = true"> Delete Order </n-button>

                    <n-modal
                        v-model:show="showDeleteConfirm"
                        preset="dialog"
                        title="Delete Replacement Order"
                        @positive-click="handleDeleteOrder"
                        @negative-click="showDeleteConfirm = false"
                        positive-text="Delete"
                        negative-text="Cancel"
                        :positive-button-props="{ type: 'error', loading: deletingOrder }"
                    >
                        <template #default>
                            Are you sure you want to delete this replacement order? This action cannot be undone.
                        </template>
                    </n-modal>
                </div>

                <!-- Restore button for archived orders -->
                <div v-if="props.isArchived" style="margin-top: 1rem">
                    <n-button size="medium" type="primary" @click="showRestoreConfirm = true"> Restore Order </n-button>

                    <n-modal
                        v-model:show="showRestoreConfirm"
                        preset="dialog"
                        title="Restore Order"
                        @positive-click="handleRestoreOrder"
                        @negative-click="showRestoreConfirm = false"
                        positive-text="Restore"
                        negative-text="Cancel"
                        :positive-button-props="{ loading: restoringOrder, disabled: restoringOrder }"
                    >
                        <template #default>
                            Are you sure you want to restore this order? It will be moved back to active orders.
                        </template>
                    </n-modal>
                </div>
            </div>

            <hr />

            <div class="customer-info">
                <h4>
                    <span>Customer info</span>
                    <span v-if="order.customer_id" style="margin-left: 1em">
                        <router-link
                            class="customer-link"
                            :to="{ name: 'customer-view', params: { id: order.customer_id } }"
                            style="font-size: 12px"
                        >
                            View Profile →
                        </router-link>
                    </span>
                </h4>

                <p v-if="editingCustomerInfo">
                    <strong>Customer: </strong>
                    <n-select
                        v-model:value="selectedCustomer"
                        :filterable="true"
                        :remote="true"
                        clearable
                        placeholder="Select a customer"
                        :loading="loadingCustomers"
                        :options="customerOptions"
                        @search="searchCustomers"
                        style="width: 100%; max-width: 400px"
                    />
                </p>

                <!-- Billing & Shipping Address -->
                <div v-if="!props.isArchived" class="address-columns">
                    <div class="address-section">
                        <div style="display: flex; align-items: center; gap: 8px">
                            <h4>Billing Address</h4>
                            <n-button v-if="editingCustomerInfo" size="tiny" @click="copyFromShipping">
                                Copy from shipping
                            </n-button>
                        </div>
                        <template v-if="editingCustomerInfo">
                            <n-input v-model:value="editableOrderInfo.billing.first_name" placeholder="First name" />
                            <n-input v-model:value="editableOrderInfo.billing.last_name" placeholder="Last name" />
                            <n-input v-model:value="editableOrderInfo.billing.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableOrderInfo.billing.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableOrderInfo.billing.city" placeholder="City" />
                            <n-input v-model:value="editableOrderInfo.billing.state" placeholder="State" />
                            <n-input v-model:value="editableOrderInfo.billing.postcode" placeholder="Postcode" />
                            <n-select
                                v-model:value="editableOrderInfo.billing.country"
                                :options="countryOptions"
                                filterable
                                placeholder="Country"
                            />
                            <n-input v-model:value="editableOrderInfo.billing.phone" placeholder="Phone" />
                            <n-input v-model:value="editableOrderInfo.billing.email" placeholder="Email" />
                        </template>
                        <template v-else>
                            <div>
                                <p>{{ order.billing?.first_name }} {{ order.billing?.last_name }}</p>
                                <p>{{ order.billing?.address_1 }}</p>
                                <p>{{ order.billing?.address_2 }}</p>
                                <p>{{ order.billing?.city }}, {{ order.billing?.postcode }}</p>
                                <p>{{ order.billing?.state }}, {{ countryName(order.billing?.country) }}</p>
                                <strong>Phone:</strong>
                                <p>{{ order.billing?.phone }}</p>
                                <strong>Email:</strong>
                                <p>{{ order.billing?.email }}</p>
                            </div>
                        </template>
                    </div>

                    <div class="address-section">
                        <div style="display: flex; align-items: center; gap: 8px">
                            <h4>Shipping Address</h4>
                            <n-button v-if="editingCustomerInfo" size="tiny" @click="copyFromBilling">
                                Copy from billing
                            </n-button>
                        </div>
                        <template v-if="editingCustomerInfo">
                            <n-input v-model:value="editableOrderInfo.shipping.first_name" placeholder="First name" />
                            <n-input v-model:value="editableOrderInfo.shipping.last_name" placeholder="Last name" />
                            <n-input v-model:value="editableOrderInfo.shipping.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableOrderInfo.shipping.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableOrderInfo.shipping.city" placeholder="City" />
                            <n-input v-model:value="editableOrderInfo.shipping.state" placeholder="State" />
                            <n-input v-model:value="editableOrderInfo.shipping.postcode" placeholder="Postcode" />
                            <n-select
                                v-model:value="editableOrderInfo.shipping.country"
                                :options="countryOptions"
                                filterable
                                placeholder="Country"
                            />
                            <n-input v-model:value="editableOrderInfo.shipping.phone" placeholder="Phone" />
                        </template>
                        <template v-else>
                            <div>
                                <p>{{ order.shipping?.first_name }} {{ order.shipping?.last_name }}</p>
                                <p>{{ order.shipping?.address_1 }}</p>
                                <p>{{ order.shipping?.address_2 }}</p>
                                <p>{{ order.shipping?.city }}, {{ order.shipping?.postcode }}</p>
                                <p>{{ order.shipping?.state }}, {{ countryName(order.shipping?.country) }}</p>
                                <div v-if="order.shipping?.phone">
                                    <strong>Phone:</strong>
                                    <p>{{ order.shipping?.phone }}</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div v-else>
                    <p><strong>Name:</strong> {{ order.billing?.first_name }} {{ order.billing?.last_name }}</p>
                    <p><strong>Email:</strong> {{ order.billing?.email }}</p>
                </div>

                <div
                    v-if="
                        (props.sourceType !== 'replacement' && $can('edit_orders_info')) ||
                        (props.sourceType === 'replacement' && $can('edit_replacement_orders'))
                    "
                    style="margin-top: 1rem"
                >
                    <template v-if="editingCustomerInfo">
                        <n-space vertical size="medium">
                            <n-checkbox v-model:checked="updateCustomerProfile">Update customer profile</n-checkbox>

                            <n-space style="margin-top: 1rem">
                                <n-button
                                    size="medium"
                                    type="primary"
                                    @click="saveCustomerInfo"
                                    :loading="savingCustomerInfo"
                                >
                                    Save
                                </n-button>
                                <n-button
                                    size="medium"
                                    ghost
                                    @click="cancelEditCustomerInfo"
                                    :disabled="savingCustomerInfo"
                                >
                                    Cancel
                                </n-button>
                            </n-space>
                        </n-space>
                    </template>
                    <template v-else-if="!props.isArchived">
                        <n-button size="medium" type="default" @click="enterEditCustomerInfo">Edit</n-button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useMessage } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";
import { countryOptions, countryName, coerceCountryToCode, makeCountryLabelComputed } from "@/utils/countryOptions";

const countryLabelMap = makeCountryLabelComputed(computed);

const props = defineProps({
    order: Object,
    loading: Boolean,
    getMeta: Function,
    isArchived: Boolean,
    sourceType: {
        type: String,
        default: "order", // or 'replacement'
    },
});

const selectedCustomer = ref(null);
const customerOptions = ref([]);
const loadingCustomers = ref(false);

const replacementReasonOptions = [
    { label: "Delivered Not Received", value: "Delivered Not Received" },
    { label: "Shipped Processing", value: "Shipped Processing" },
    { label: "Shipped In transit", value: "Shipped In transit" },
    { label: "RTS", value: "RTS" },
    { label: "Damaged in Transit", value: "Damaged in Transit" },
    { label: "Defective product", value: "Defective product" },
    { label: "Complimentary", value: "Complimentary" },
    { label: "Empty Box", value: "Empty Box" },
    { label: "Incorrect Address", value: "Incorrect Address" },
    { label: "Missing Item", value: "Missing Item" },
    { label: "Exchange/wrong product", value: "Exchange/wrong product" },
    { label: "In lieu of refund", value: "In lieu of refund" },
    { label: "Influencer ", value: "Influencer" },
    { label: "Amazon", value: "Amazon" },
];

/** Braintree state */
const btTransaction = ref(null);
const btDisputes = ref([]);
const loadingBraintree = ref(false);

function isBraintreeOrder(order) {
    const pm = order?.payment_method || order?.payment_method_title || "";
    return String(pm).toLowerCase().includes("braintree");
}

const shouldShowBraintreeColumn = computed(() => {
    if (loadingBraintree.value) return true;
    return !!btTransaction.value || btDisputes.value.length > 0;
});

const riskTagType = computed(() => {
    const decision = btTransaction.value?.risk?.decision?.toLowerCase() || "";
    if (decision.includes("approve") || decision.includes("accepted")) return "success";
    if (decision.includes("review")) return "warning";
    if (decision.includes("decline") || decision.includes("rejected")) return "error";
    return "default";
});

function disputeTagType(status) {
    const s = (status || "").toLowerCase();
    if (s.includes("won") || s.includes("accepted")) return "success";
    if (s.includes("open") || s.includes("pending") || s.includes("needs")) return "warning";
    if (s.includes("lost") || s.includes("chargeback") || s.includes("expired")) return "error";
    return "default";
}

async function searchCustomers(query) {
    if (!isValidEmail(query)) {
        customerOptions.value = [];
        return;
    }
    loadingCustomers.value = true;
    try {
        const res = await request({
            url: "/customers/search",
            params: { type: "email", value: query },
        });
        customerOptions.value = res.map((user) => ({
            label: `${user.name} (#${user.id} – ${user.email})`,
            value: user.id,
            raw: user,
        }));
    } catch (err) {
        console.error("❌ Failed to search customers", err);
        customerOptions.value = [];
    } finally {
        loadingCustomers.value = false;
    }
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}
function renderCustomerOption(option) {
    return option.label;
}

const emit = defineEmits(["updateOrder"]);
const message = useMessage();
const router = useRouter();
const route = useRoute();

const baseUrl = props.sourceType === "replacement" ? "/replacements" : "/orders";

const editingCustomerInfo = ref(false);
const editingOrderInfo = ref(false);

const savingOrderInfo = ref(false);
const savingCustomerInfo = ref(false);

const updateCustomerProfile = ref(false);

// Restore confirmation dialog state
const showRestoreConfirm = ref(false);
const restoringOrder = ref(false);

const editableOrderInfo = ref({
    status: "",
    newOrReturning: "",
    replacementReason: "",
    chargebackAlert: "no",
    billing: {
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        address_1: "",
        address_2: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
    },
    shipping: {
        first_name: "",
        last_name: "",
        phone: "",
        address_1: "",
        address_2: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
    },
});

const formattedCreatedDate = computed(() =>
    props.order?.date_created ? formatOrderDate(props.order.date_created.date) : "—"
);

// 👉 No need to manually extract meta
const newOrReturning = computed(() => props.getMeta?.("new_or_returning") || "—");

const statusOptions = [
    { label: "Pending", value: "pending" },
    { label: "Processing", value: "processing" },
    { label: "On Hold", value: "on-hold" },
    { label: "Completed", value: "completed" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Refunded", value: "refunded" },
    { label: "Failed", value: "failed" },
];

const newOrReturningOptions = [
    { label: "New", value: "new" },
    { label: "Returning", value: "returning" },
];

const chargebackAlertOptions = [
    { label: "Yes", value: "yes" },
    { label: "No", value: "no" },
];

const billingKeys = [
    "first_name",
    "last_name",
    "email",
    "phone",
    "address_1",
    "address_2",
    "city",
    "state",
    "postcode",
    "country",
];

const shippingKeys = [
    "first_name",
    "last_name",
    "address_1",
    "phone",
    "address_2",
    "city",
    "state",
    "postcode",
    "country",
];

const hotjarLinks = computed(() => {
    const urls = [];

    // try last recording first
    const last = props.getMeta?.("_hotjar_last_recording_url");
    if (last) urls.push(last);

    // then all recordings (array or JSON string)
    let all = props.getMeta?.("_hotjar_recording_urls");
    if (typeof all === "string") {
        try {
            all = JSON.parse(all);
        } catch {
            all = [];
        }
    }
    if (Array.isArray(all)) {
        for (const u of all) if (u && !urls.includes(u)) urls.push(u);
    }

    return urls;
});

watch(
    () => props.order,
    async (val) => {
        if (!val) return;

        editableOrderInfo.value.status = val.status;
        editableOrderInfo.value.newOrReturning = props.getMeta?.("new_or_returning") || "";
        editableOrderInfo.value.replacementReason = props.getMeta?.("replacement_reason") || "";
        editableOrderInfo.value.chargebackAlert = props.getMeta?.("chargeback_alert_received") || "no";

        billingKeys.forEach((key) => {
            const raw = val.billing?.[key] || "";
            editableOrderInfo.value.billing[key] = key === "country" ? coerceCountryToCode(raw) : raw;
        });
        shippingKeys.forEach((key) => {
            const raw = val.shipping?.[key] || "";
            editableOrderInfo.value.shipping[key] = key === "country" ? coerceCountryToCode(raw) : raw;
        });

        if (val.customer_id) {
            selectedCustomer.value = val.customer_id;
            customerOptions.value = [
                {
                    label: `${val.customer_profile?.first_name || ""} ${val.customer_profile?.last_name || ""} (#${
                        val.customer_id
                    } – ${val.customer_profile?.email || ""})`,
                    value: val.customer_id,
                },
            ];
        }

        // Braintree fetch (only if looks like BT and we have a transaction ID)
        btTransaction.value = null;
        btDisputes.value = [];
        if (val.transaction_id && isBraintreeOrder(val)) {
            loadingBraintree.value = true;
            try {
                const res = await request({
                    url: `/orders/braintree-info`,
                    method: "POST",
                    body: { transaction_id: val.transaction_id },
                });
                btTransaction.value = res?.transaction || null;
                btDisputes.value = Array.isArray(res?.disputes) ? res.disputes : [];
                console.log("✅ Braintree info loaded", { tx: btTransaction.value, disputes: btDisputes.value });
            } catch (err) {
                console.error("⚠️ Failed to load Braintree info", err);
                btTransaction.value = null;
                btDisputes.value = [];
            } finally {
                loadingBraintree.value = false;
            }
        }
    },
    { immediate: true }
);

watch(selectedCustomer, (newVal) => {
    const selected = customerOptions.value.find((opt) => opt.value === newVal);
    if (!selected || !selected.raw) return;

    const { billing, shipping } = selected.raw;

    // Autofill billing if fields are empty
    billingKeys.forEach((key) => {
        if (!editableOrderInfo.value.billing[key]) {
            editableOrderInfo.value.billing[key] = billing?.[key] || "";
        }
    });

    // Autofill shipping if fields are empty
    shippingKeys.forEach((key) => {
        if (!editableOrderInfo.value.shipping[key]) {
            editableOrderInfo.value.shipping[key] = shipping?.[key] || "";
        }
    });
});

function enterEditOrderInfo() {
    editableOrderInfo.value.status = props.order.status;
    editableOrderInfo.value.newOrReturning = newOrReturning.value;
    editingOrderInfo.value = true;
}

function cancelEditOrderInfo() {
    editingOrderInfo.value = false;
}

async function saveOrderInfo() {
    savingOrderInfo.value = true;

    const payload = {
        status: editableOrderInfo.value.status,
        meta: {
            new_or_returning: editableOrderInfo.value.newOrReturning,
            replacement_reason: editableOrderInfo.value.replacementReason,
            chargeback_alert_received: editableOrderInfo.value.chargebackAlert,
        },
    };

    try {
        await request({
            url: `${baseUrl}/${props.order.id}`,
            method: "POST",
            body: payload,
        });

        message.success("Order updated");
        editingOrderInfo.value = false;
        emit("updateOrder");
    } catch (err) {
        message.error("Update failed");
        console.error(err);
    } finally {
        savingOrderInfo.value = false;
    }
}

function enterEditCustomerInfo() {
    editingCustomerInfo.value = true;
}

function cancelEditCustomerInfo() {
    editingCustomerInfo.value = false;
}

async function saveCustomerInfo() {
    savingCustomerInfo.value = true;

    const payload = {
        billing: {
            ...editableOrderInfo.value.billing,
            country: coerceCountryToCode(editableOrderInfo.value.billing.country),
        },
        shipping: {
            ...editableOrderInfo.value.shipping,
            country: coerceCountryToCode(editableOrderInfo.value.shipping.country),
        },
        update_customer_profile: updateCustomerProfile.value,
    };
    if (selectedCustomer.value && selectedCustomer.value !== props.order.customer_id) {
        payload.customer_id = selectedCustomer.value;
    }
    try {
        await request({
            url: `${baseUrl}/${props.order.id}`,
            method: "POST",
            body: payload,
        });

        message.success("Customer info updated");
        editingCustomerInfo.value = false;
        emit("updateOrder");
    } catch (err) {
        message.error("Update failed");
        console.error(err);
    } finally {
        savingCustomerInfo.value = false;
    }
}

// Restore Order logic
async function handleRestoreOrder() {
    restoringOrder.value = true;
    try {
        await request({
            url: `/orders/${props.order.id}/restore`,
            method: "POST",
        });
        message.success("Order restored");
        // Remove is_archived query param & refresh route
        const newQuery = { ...route.query };
        delete newQuery.is_archived;
        router.replace({ query: newQuery });
        emit("updateOrder");
    } catch (err) {
        message.error("Failed to restore order");
        console.error(err);
    } finally {
        restoringOrder.value = false;
        showRestoreConfirm.value = false;
    }
}

const showDeleteConfirm = ref(false);
const deletingOrder = ref(false);

async function handleDeleteOrder() {
    deletingOrder.value = true;
    try {
        await request({
            url: `/replacements/${props.order.id}`,
            method: "DELETE",
        });

        message.success("Replacement order deleted");
        router.push({ name: "replacements" }); // or wherever makes sense
    } catch (err) {
        message.error("Failed to delete replacement order");
        console.error(err);
    } finally {
        deletingOrder.value = false;
        showDeleteConfirm.value = false;
    }
}

function copyFromShipping() {
    const keys = ["first_name", "last_name", "phone", "address_1", "address_2", "city", "state", "postcode", "country"];
    keys.forEach((key) => {
        editableOrderInfo.value.billing[key] = editableOrderInfo.value.shipping[key] || "";
    });
    message.success("Copied from shipping 📦 → 🧾");
}

function copyFromBilling() {
    const keys = ["first_name", "last_name", "phone", "address_1", "address_2", "city", "state", "postcode", "country"];
    keys.forEach((key) => {
        editableOrderInfo.value.shipping[key] = editableOrderInfo.value.billing[key] || "";
    });
    message.success("Copied from billing 🧾 → 📦");
}
</script>
