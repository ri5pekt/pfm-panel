<template>
    <div class="panel general-info-panel">
        <h3>General Info</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <div class="subscription-info">
                <h4>Subscription Info</h4>
                <p><strong>ID:</strong> {{ subscription.id }}</p>
                <p>
                    <strong>Status: </strong>
                    <template v-if="editingSubscriptionInfo">
                        <n-select
                            v-model:value="editableSubscriptionInfo.status"
                            :options="statusOptions"
                            style="width: 180px"
                        />
                    </template>
                    <template v-else>
                        <span :class="'subscription-status ' + subscription.status">{{ subscription.status }}</span>
                    </template>
                </p>
                <p><strong>Start Date: </strong> {{ subscription.start_date || "—" }}</p>
                <p>
                    <strong>Next Payment Date: </strong>
                    <template v-if="editingSubscriptionInfo">
                        <n-date-picker
                            v-model:value="editableSubscriptionInfo.next_payment_date"
                            type="datetime"
                            :default-value="editableSubscriptionInfo.next_payment_date"
                            style="width: 260px"
                            clearable
                        />
                    </template>
                    <template v-else>
                        {{ subscription.next_payment_date || "—" }}
                    </template>
                </p>
                <p><strong>Last Order Date: </strong> {{ subscription.last_order_date || "—" }}</p>
                <p><strong>Recurring Total: </strong> <span v-html="subscription.recurring_total"></span></p>
                <p>
                    <strong>Frequency: </strong>
                    <template v-if="editingSubscriptionInfo">
                        <div class="row" style="display: flex; align-items: center; gap: 4px">
                            <span>Every</span>
                            <n-select
                                v-model:value="editableSubscriptionInfo.billing_interval"
                                :options="intervalOptions"
                                style="width: 70px; margin: 0 8px"
                            />
                            <n-select
                                v-model:value="editableSubscriptionInfo.billing_period"
                                :options="periodOptions"
                                style="width: 100px"
                            />
                        </div>
                    </template>
                    <template v-else>
                        Every {{ subscription.billing_interval }} {{ subscription.billing_period
                        }}<span v-if="subscription.billing_interval > 1">s</span>
                    </template>
                </p>
                <p><strong>Payment Method: </strong> {{ subscription.payment_method_title || "—" }}</p>

                <div v-if="$can('edit_subscriptions')" style="margin-top: 1rem">
                    <template v-if="editingSubscriptionInfo">
                        <n-button
                            size="medium"
                            type="primary"
                            :loading="savingSubscriptionInfo"
                            @click="saveSubscriptionInfo"
                            >Save</n-button
                        >
                        <n-button
                            size="medium"
                            ghost
                            style="margin-left: 0.5rem"
                            @click="cancelEditSubscriptionInfo"
                            :disabled="savingSubscriptionInfo"
                            >Cancel</n-button
                        >
                    </template>
                    <template v-else>
                        <n-button size="medium" @click="enterEditSubscriptionInfo">Edit</n-button>
                    </template>
                </div>
            </div>
            <hr />

            <div class="customer-info">
                <h4>
                    <span>Customer info</span>
                    <span v-if="subscription.customer.id" style="margin-left: 1em">
                        <router-link
                            class="customer-link"
                            :to="{ name: 'customer-view', params: { id: subscription.customer.id } }"
                            style="font-size: 12px"
                        >
                            View Profile →
                        </router-link>
                    </span>
                </h4>
                <p>
                    <strong>Name: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableCustomerInfo.billing.first_name"
                            placeholder="First name"
                            style="width: 130px; margin-right: 8px"
                        />
                        <n-input
                            v-model:value="editableCustomerInfo.billing.last_name"
                            placeholder="Last name"
                            style="width: 130px"
                        />
                    </template>
                    <template v-else
                        >&nbsp;{{ subscription.billing?.first_name }} {{ subscription.billing?.last_name }}
                    </template>
                </p>

                <p>
                    <strong>Email: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableCustomerInfo.billing.email"
                            type="email"
                            placeholder="Email"
                            style="width: 270px"
                        />
                    </template>
                    <template v-else>&nbsp;{{ subscription.billing?.email }}</template>
                </p>

                <p>
                    <strong>Phone: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableCustomerInfo.billing.phone"
                            placeholder="Phone"
                            style="width: 200px"
                        />
                    </template>
                    <template v-else>&nbsp;{{ subscription.billing?.phone }}</template>
                </p>

                <div class="address-columns">
                    <div class="address-section">
                        <h4>Billing Address</h4>
                        <template v-if="editingCustomerInfo">
                            <n-input v-model:value="editableCustomerInfo.billing.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableCustomerInfo.billing.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableCustomerInfo.billing.city" placeholder="City" />
                            <n-input v-model:value="editableCustomerInfo.billing.state" placeholder="State" />
                            <n-input v-model:value="editableCustomerInfo.billing.postcode" placeholder="Postcode" />
                            <n-input v-model:value="editableCustomerInfo.billing.country" placeholder="Country" />
                        </template>
                        <template v-else>
                            <p>
                                {{ subscription.billing?.address_1 }}<br />
                                {{ subscription.billing?.address_2 }}<br />
                                {{ subscription.billing?.city }}, {{ subscription.billing?.postcode }}<br />
                                {{ subscription.billing?.state }}, {{ subscription.billing?.country }}
                            </p>
                        </template>
                    </div>

                    <div class="address-section">
                        <h4>Shipping Address</h4>
                        <template v-if="editingCustomerInfo">
                            <n-input
                                v-model:value="editableCustomerInfo.shipping.first_name"
                                placeholder="First name"
                            />
                            <n-input v-model:value="editableCustomerInfo.shipping.last_name" placeholder="Last name" />
                            <n-input v-model:value="editableCustomerInfo.shipping.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableCustomerInfo.shipping.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableCustomerInfo.shipping.city" placeholder="City" />
                            <n-input v-model:value="editableCustomerInfo.shipping.state" placeholder="State" />
                            <n-input v-model:value="editableCustomerInfo.shipping.postcode" placeholder="Postcode" />
                            <n-input v-model:value="editableCustomerInfo.shipping.country" placeholder="Country" />
                        </template>
                        <template v-else>
                            <p>
                                {{ subscription.shipping?.first_name }} {{ subscription.shipping?.last_name }}<br />
                                {{ subscription.shipping?.address_1 }}<br />
                                {{ subscription.shipping?.address_2 }}<br />
                                {{ subscription.shipping?.city }}, {{ subscription.shipping?.postcode }}<br />
                                {{ subscription.shipping?.state }}, {{ subscription.shipping?.country }}
                            </p>
                        </template>
                    </div>
                </div>

                <div style="margin-top: 1rem" v-if="$can('edit_subscriptions')">
                    <template v-if="editingCustomerInfo">
                        <n-space vertical size="medium">
                            <n-checkbox v-model:checked="updateCustomerProfile">Update customer profile</n-checkbox>
                            <n-checkbox v-model:checked="updateAllSubscriptions">
                                Update all user Subscriptions
                            </n-checkbox>
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
                    <template v-else>
                        <n-button size="medium" @click="enterEditCustomerInfo">Edit</n-button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";

const props = defineProps({
    subscription: Object,
    loading: Boolean,
});
const emit = defineEmits(["update-subscription"]);

const message = useMessage();

const editingSubscriptionInfo = ref(false);
const editingCustomerInfo = ref(false);

const savingSubscriptionInfo = ref(false);
const savingCustomerInfo = ref(false);

const updateCustomerProfile = ref(false);
const updateAllSubscriptions = ref(false);

const editableSubscriptionInfo = ref({
    status: "",
    next_payment_date: null,
    billing_interval: 1,
    billing_period: "",
});

const editableCustomerInfo = ref({
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
        address_1: "",
        address_2: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
    },
});

const statusOptions = [
    { label: "Active", value: "active" },
    { label: "Pending Cancellation", value: "pending-cancel" },
    { label: "On Hold", value: "on-hold" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Expired", value: "expired" },
    { label: "Pending", value: "pending" },
];

const intervalOptions = [
    { label: "1", value: 1 },
    { label: "2", value: 2 },
    { label: "3", value: 3 },
    { label: "4", value: 4 },
    { label: "5", value: 5 },
    { label: "6", value: 6 },
];

const periodOptions = [
    { label: "Day", value: "day" },
    { label: "Week", value: "week" },
    { label: "Month", value: "month" },
    { label: "Year", value: "year" },
];

watch(
    () => props.subscription,
    (val) => {
        if (!val) return;

        // Main info
        editableSubscriptionInfo.value.status = val.status;
        editableSubscriptionInfo.value.next_payment_date = val.next_payment_date
            ? new Date(val.next_payment_date.replace(/-/g, "/")).getTime()
            : null;
        editableSubscriptionInfo.value.billing_interval = Number(val.billing_interval) || 1;
        editableSubscriptionInfo.value.billing_period = val.billing_period || "month";

        // Customer info
        Object.assign(editableCustomerInfo.value.billing, val.billing || {});
        Object.assign(editableCustomerInfo.value.shipping, val.shipping || {});

        updateCustomerProfile.value = false;
    },
    { immediate: true }
);

function enterEditSubscriptionInfo() {
    editingSubscriptionInfo.value = true;
}
function cancelEditSubscriptionInfo() {
    editingSubscriptionInfo.value = false;
    // Reset editable fields to current values
    editableSubscriptionInfo.value.status = props.subscription.status;
    editableSubscriptionInfo.value.next_payment_date = props.subscription.next_payment_date
        ? new Date(props.subscription.next_payment_date.replace(/-/g, "/")).getTime()
        : null;
    editableSubscriptionInfo.value.billing_interval = Number(props.subscription.billing_interval) || 1;
    editableSubscriptionInfo.value.billing_period = props.subscription.billing_period || "month";
}

function formatUtcDatetime(ms) {
    if (!ms) return null;
    const date = new Date(ms);
    const pad = (n) => n.toString().padStart(2, "0");
    return (
        date.getUTCFullYear() +
        "-" +
        pad(date.getUTCMonth() + 1) +
        "-" +
        pad(date.getUTCDate()) +
        " " +
        pad(date.getUTCHours()) +
        ":" +
        pad(date.getUTCMinutes()) +
        ":" +
        pad(date.getUTCSeconds())
    );
}

async function saveSubscriptionInfo() {
    savingSubscriptionInfo.value = true;
    try {
        await request({
            url: `/subscriptions/${props.subscription.id}`,
            method: "POST",
            body: {
                status: editableSubscriptionInfo.value.status,
                next_payment_date: editableSubscriptionInfo.value.next_payment_date
                    ? formatUtcDatetime(editableSubscriptionInfo.value.next_payment_date)
                    : null,
                billing_interval: editableSubscriptionInfo.value.billing_interval,
                billing_period: editableSubscriptionInfo.value.billing_period,
            },
        });
        message.success("Subscription info updated");
        editingSubscriptionInfo.value = false;
        emit("update-subscription");
    } catch (err) {
        message.error("Failed to update subscription info");
        console.error(err);
    } finally {
        savingSubscriptionInfo.value = false;
    }
}

// ----- Customer Info Edit -----
function enterEditCustomerInfo() {
    editingCustomerInfo.value = true;
}
function cancelEditCustomerInfo() {
    editingCustomerInfo.value = false;
    Object.assign(editableCustomerInfo.value.billing, props.subscription.billing || {});
    Object.assign(editableCustomerInfo.value.shipping, props.subscription.shipping || {});
    updateCustomerProfile.value = false;
    updateAllSubscriptions.value = false;
}

async function saveCustomerInfo() {
    savingCustomerInfo.value = true;
    try {
        await request({
            url: `/subscriptions/${props.subscription.id}`,
            method: "POST",
            body: {
                billing: editableCustomerInfo.value.billing,
                shipping: editableCustomerInfo.value.shipping,
                update_customer_profile: updateCustomerProfile.value ? 1 : 0,
                update_all_subscriptions: updateAllSubscriptions.value ? 1 : 0,
            },
        });
        message.success("Customer info updated");
        editingCustomerInfo.value = false;
        emit("update-subscription");
    } catch (err) {
        message.error("Failed to update customer info");
        console.error(err);
    } finally {
        savingCustomerInfo.value = false;
    }
}
</script>
