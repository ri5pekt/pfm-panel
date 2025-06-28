<!-- OrderInfoPanel.vue -->
<template>
    <div class="panel general-info-panel">
        <h3>General Info</h3>
        <n-skeleton v-if="loading" text :repeat="5" />
        <div v-else>
            <div class="order-info">
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

                <p><strong>Date Created:</strong> {{ formattedCreatedDate }}</p>

                <p>
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

                <p><strong>Payment Method:</strong> {{ order.payment_method_title }}</p>
                <p><strong>Transaction ID:</strong> {{ order.transaction_id }}</p>

                <div v-if="$can('edit_orders_info')" style="margin-top: 1rem">
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
                            >Cancel</n-button
                        >
                    </template>
                    <template v-else>
                        <n-button size="medium" type="default" @click="enterEditOrderInfo">Edit</n-button>
                    </template>
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
                <p>
                    <strong>Name: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableOrderInfo.billing.first_name"
                            placeholder="First name"
                            style="width: 130px; margin-right: 8px"
                        />
                        <n-input
                            v-model:value="editableOrderInfo.billing.last_name"
                            placeholder="Last name"
                            style="width: 130px"
                        />
                    </template>
                    <template v-else> {{ order.billing?.first_name }} {{ order.billing?.last_name }} </template>
                </p>

                <p>
                    <strong>Email: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableOrderInfo.billing.email"
                            type="email"
                            placeholder="Email"
                            style="width: 270px"
                        />
                    </template>
                    <template v-else>&nbsp;{{ order.billing?.email }}</template>
                </p>

                <p>
                    <strong>Phone: </strong>
                    <template v-if="editingCustomerInfo">
                        <n-input
                            v-model:value="editableOrderInfo.billing.phone"
                            placeholder="Phone"
                            style="width: 200px"
                        />
                    </template>
                    <template v-else>&nbsp;{{ order.billing?.phone }}</template>
                </p>

                <!-- Billing & Shipping Address -->
                <div class="address-columns">
                    <div class="address-section">
                        <h4>Billing Address</h4>
                        <template v-if="editingCustomerInfo">
                            <n-input v-model:value="editableOrderInfo.billing.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableOrderInfo.billing.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableOrderInfo.billing.city" placeholder="City" />
                            <n-input v-model:value="editableOrderInfo.billing.state" placeholder="State" />
                            <n-input v-model:value="editableOrderInfo.billing.postcode" placeholder="Postcode" />
                            <n-input v-model:value="editableOrderInfo.billing.country" placeholder="Country" />
                        </template>
                        <template v-else>
                            <p>
                                {{ order.billing?.address_1 }}<br />
                                {{ order.billing?.address_2 }}<br />
                                {{ order.billing?.city }}, {{ order.billing?.postcode }}<br />
                                {{ order.billing?.state }}, {{ order.billing?.country }}
                            </p>
                        </template>
                    </div>

                    <div class="address-section">
                        <h4>Shipping Address</h4>
                        <template v-if="editingCustomerInfo">
                            <n-input v-model:value="editableOrderInfo.shipping.first_name" placeholder="First name" />
                            <n-input v-model:value="editableOrderInfo.shipping.last_name" placeholder="Last name" />
                            <n-input v-model:value="editableOrderInfo.shipping.phone" placeholder="Phone" />
                            <n-input v-model:value="editableOrderInfo.shipping.address_1" placeholder="Address 1" />
                            <n-input v-model:value="editableOrderInfo.shipping.address_2" placeholder="Address 2" />
                            <n-input v-model:value="editableOrderInfo.shipping.city" placeholder="City" />
                            <n-input v-model:value="editableOrderInfo.shipping.state" placeholder="State" />
                            <n-input v-model:value="editableOrderInfo.shipping.postcode" placeholder="Postcode" />
                            <n-input v-model:value="editableOrderInfo.shipping.country" placeholder="Country" />
                        </template>
                        <template v-else>
                            <p>
                                {{ order.shipping?.first_name }} {{ order.shipping?.last_name }}<br />
                                {{ order.shipping?.phone }}<br />
                                {{ order.shipping?.address_1 }}<br />
                                {{ order.shipping?.address_2 }}<br />
                                {{ order.shipping?.city }}, {{ order.shipping?.postcode }}<br />
                                {{ order.shipping?.state }}, {{ order.shipping?.country }}
                            </p>
                        </template>
                    </div>
                </div>

                <div v-if="$can('edit_orders_info')" style="margin-top: 1rem">
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
                    <template v-else>
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
import { request } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";

const props = defineProps({
    order: Object,
    loading: Boolean,
    getMeta: Function, // 👈 new prop
});

const emit = defineEmits(["updateOrder"]);

const message = useMessage();

const editingCustomerInfo = ref(false);
const editingOrderInfo = ref(false);

const savingOrderInfo = ref(false);
const savingCustomerInfo = ref(false);

const updateCustomerProfile = ref(false);

const editableOrderInfo = ref({
    status: "",
    newOrReturning: "",
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

watch(
    () => props.order,
    (val) => {
        if (!val) return;

        editableOrderInfo.value.status = val.status;
        editableOrderInfo.value.newOrReturning = props.getMeta?.("new_or_returning") || "";

        billingKeys.forEach((key) => {
            editableOrderInfo.value.billing[key] = val.billing?.[key] || "";
        });

        shippingKeys.forEach((key) => {
            editableOrderInfo.value.shipping[key] = val.shipping?.[key] || "";
        });
    },
    { immediate: true }
);

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
        },
    };

    try {
        await request({
            url: `/orders/${props.order.id}`,
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
        billing: editableOrderInfo.value.billing,
        shipping: editableOrderInfo.value.shipping,
        update_customer_profile: updateCustomerProfile.value,
    };

    try {
        await request({
            url: `/orders/${props.order.id}`,
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
</script>
