<!-- CustomerInfoPanel.vue -->
<template>
    <div class="panel customer-info-panel">
        <h3>Customer Info</h3>
        <n-skeleton v-if="loading" text :repeat="8" />
        <div v-else-if="customer">
            <div>
                <p><strong>ID: </strong> {{ customer.id }}</p>

                <p><strong>Orders: </strong> {{ customer.orders_count }}</p>
                <p><strong>Registered: </strong> {{ customer.registered }}</p>
                <p><strong>Last Order Date: </strong> {{ customer.last_order_date || "—" }}</p>
            </div>
            <div class="address-columns" style="margin-top: 1rem">
                <div class="address-section">
                    <div style="display: flex; align-items: center; gap: 8px">
                        <h4>Billing Address</h4>
                        <n-button v-if="editing" size="tiny" @click="copyFromShipping"> Copy from shipping </n-button>
                    </div>
                    <template v-if="editing">
                        <n-input v-model:value="editable.billing.first_name" placeholder="First name" />
                        <n-input v-model:value="editable.billing.last_name" placeholder="Last name" />
                        <n-input v-model:value="editable.billing.address_1" placeholder="Address 1" />
                        <n-input v-model:value="editable.billing.address_2" placeholder="Address 2" />
                        <n-input v-model:value="editable.billing.city" placeholder="City" />
                        <n-input v-model:value="editable.billing.state" placeholder="State" />
                        <n-input v-model:value="editable.billing.postcode" placeholder="Postcode" />
                        <n-input v-model:value="editable.billing.country" placeholder="Country" />
                        <n-input v-model:value="editable.billing.phone" placeholder="Phone" />
                        <n-input v-model:value="editable.billing.email" placeholder="Email" />
                    </template>
                    <template v-else>
                        <div>
                            <p>{{ customer.billing?.first_name }} {{ customer.billing?.last_name }}</p>
                            <p>{{ customer.billing?.address_1 }}</p>
                            <p>{{ customer.billing?.address_2 }}</p>
                            <p>{{ customer.billing?.city }}, {{ customer.billing?.postcode }}</p>
                            <p>{{ customer.billing?.state }}, {{ customer.billing?.country }}</p>
                            <strong>Phone:</strong>
                            <p>{{ customer.billing?.phone }}</p>
                            <strong>Email:</strong>
                            <p>{{ customer.billing?.email }}</p>
                        </div>
                    </template>
                </div>
                <div class="address-section">
                    <div style="display: flex; align-items: center; gap: 8px">
                        <h4>Shipping Address</h4>
                        <n-button v-if="editing" size="tiny" @click="copyFromBilling"> Copy from billing </n-button>
                    </div>
                    <template v-if="editing">
                        <n-input v-model:value="editable.shipping.first_name" placeholder="First name" />
                        <n-input v-model:value="editable.shipping.last_name" placeholder="Last name" />
                        <n-input v-model:value="editable.shipping.address_1" placeholder="Address 1" />
                        <n-input v-model:value="editable.shipping.address_2" placeholder="Address 2" />
                        <n-input v-model:value="editable.shipping.city" placeholder="City" />
                        <n-input v-model:value="editable.shipping.state" placeholder="State" />
                        <n-input v-model:value="editable.shipping.postcode" placeholder="Postcode" />
                        <n-input v-model:value="editable.shipping.country" placeholder="Country" />
                        <n-input v-model:value="editable.shipping.phone" placeholder="Phone" />
                    </template>
                    <template v-else>
                        <div>
                            <p>{{ customer.shipping?.first_name }} {{ customer.shipping?.last_name }}</p>
                            <p>{{ customer.shipping?.address_1 }}</p>
                            <p>{{ customer.shipping?.address_2 }}</p>
                            <p>{{ customer.shipping?.city }}, {{ customer.shipping?.postcode }}</p>
                            <p>{{ customer.shipping?.state }}, {{ customer.shipping?.country }}</p>
                            <div v-if="customer.shipping?.phone">
                                <strong>Phone:</strong>
                                <p>{{ customer.shipping?.phone }}</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div v-if="$can('edit_users')" style="margin-top: 1rem">
                <template v-if="editing">
                    <n-space vertical size="medium">
                        <n-checkbox v-model:checked="updateAllOrders">Update all user Orders</n-checkbox>
                        <n-checkbox v-model:checked="updateAllSubscriptions">Update all user Subscriptions</n-checkbox>
                        <n-space style="margin-top: 1rem">
                            <n-button size="medium" type="primary" @click="save" :loading="saving">Save</n-button>
                            <n-button size="medium" ghost @click="cancel" :disabled="saving">Cancel</n-button>
                        </n-space>
                    </n-space>
                </template>
                <template v-else>
                    <n-space>
                        <n-button size="medium" @click="enterEdit">Edit</n-button>
                        <n-button v-if="$can('assume_user')" :loading="assumeUserLoading" @click="assumeUser"
                            >Login as This Customer
                        </n-button>
                    </n-space>
                </template>
            </div>
        </div>
        <div v-else>
            <n-alert type="error">Customer not found.</n-alert>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";

const props = defineProps({
    customer: Object,
    loading: Boolean,
});
const assumeUserLoading = ref(false);

const emit = defineEmits(["updateCustomer"]);

const message = useMessage();

const editing = ref(false);
const saving = ref(false);

const editable = ref({
    email: "",
    billing: {},
    shipping: {},
});

watch(
    () => props.customer,
    (val) => {
        if (!val) return;
        editable.value.email = val.email || "";
        editable.value.billing = { ...(val.billing || {}) };
        editable.value.shipping = { ...(val.shipping || {}) };
    },
    { immediate: true }
);

function enterEdit() {
    editing.value = true;
}

function cancel() {
    editing.value = false;
    // Reset fields to original
    if (props.customer) {
        editable.value.email = props.customer.email || "";
        editable.value.billing = { ...(props.customer.billing || {}) };
        editable.value.shipping = { ...(props.customer.shipping || {}) };
    }
}
const updateAllOrders = ref(false);
const updateAllSubscriptions = ref(false);

async function save() {
    saving.value = true;
    try {
        await request({
            url: `/customers/${props.customer.id}`,
            method: "POST",
            body: {
                // For backend compatibility, also send first/last name as billing fields
                first_name: editable.value.billing.first_name,
                last_name: editable.value.billing.last_name,
                email: editable.value.email,
                billing: editable.value.billing,
                shipping: editable.value.shipping,
                update_all_orders: updateAllOrders.value,
                update_all_subscriptions: updateAllSubscriptions.value,
            },
        });
        message.success("Customer updated");
        editing.value = false;
        emit("updateCustomer");
    } catch (err) {
        message.error("Update failed");
        console.error(err);
    } finally {
        saving.value = false;
    }
}

async function assumeUser() {
    assumeUserLoading.value = true;
    try {
        const res = await request({
            url: `/customers/${props.customer.id}/assume_user`,
            method: "POST",
        });
        if (res.switch_url) {
            console.log(res.switch_url);
            window.location.href = res.switch_url;
            assumeUserLoading.value = false;
        } else {
            console.log("Switch URL not returned:", res);
            assumeUserLoading.value = false;
        }
    } catch (err) {
        console.error("Assume user failed:", err);
        assumeUserLoading.value = false;
    }
}

function copyFromShipping() {
    const keys = ["first_name", "last_name", "phone", "address_1", "address_2", "city", "state", "postcode", "country"];
    keys.forEach((key) => {
        editable.value.billing[key] = editable.value.shipping[key] || "";
    });
    message.success("Copied from shipping 📦 → 🧾");
}

function copyFromBilling() {
    const keys = ["first_name", "last_name", "phone", "address_1", "address_2", "city", "state", "postcode", "country"];
    keys.forEach((key) => {
        editable.value.shipping[key] = editable.value.billing[key] || "";
    });
    message.success("Copied from billing 🧾 → 📦");
}
</script>
