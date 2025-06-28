<template>
    <div class="panel customer-info-panel">
        <h3>Customer Info</h3>
        <n-skeleton v-if="loading" text :repeat="8" />
        <div v-else-if="customer">
            <div>
                <p><strong>ID: </strong> {{ customer.id }}</p>
                <p>
                    <strong>Name: </strong>
                    <template v-if="editing">
                        <n-input
                            v-model:value="editable.billing.first_name"
                            placeholder="First name"
                            style="width: 120px; margin-right: 8px"
                        />
                        <n-input
                            v-model:value="editable.billing.last_name"
                            placeholder="Last name"
                            style="width: 120px"
                        />
                    </template>
                    <template v-else>{{ customer.billing?.first_name }} {{ customer.billing?.last_name }}</template>
                </p>
                <p>
                    <strong>Email: </strong>
                    <template v-if="editing">
                        <n-input v-model:value="editable.email" type="email" placeholder="Email" style="width: 250px" />
                    </template>
                    <template v-else>{{ customer.email }}</template>
                </p>
                <p>
                    <strong>Phone: </strong>
                    <template v-if="editing">
                        <n-input v-model:value="editable.billing.phone" placeholder="Phone" style="width: 180px" />
                    </template>
                    <template v-else>{{ customer.billing?.phone || "—" }}</template>
                </p>
                <p><strong>Orders: </strong> {{ customer.orders_count }}</p>
                <p><strong>Registered: </strong> {{ customer.registered }}</p>
                <p><strong>Last Order Date: </strong> {{ customer.last_order_date || "—" }}</p>
            </div>
            <div class="address-columns" style="margin-top: 1rem">
                <div class="address-section">
                    <h4>Billing Address</h4>
                    <template v-if="editing">
                        <n-input v-model:value="editable.billing.address_1" placeholder="Address 1" />
                        <n-input v-model:value="editable.billing.address_2" placeholder="Address 2" />
                        <n-input v-model:value="editable.billing.city" placeholder="City" />
                        <n-input v-model:value="editable.billing.state" placeholder="State" />
                        <n-input v-model:value="editable.billing.postcode" placeholder="Postcode" />
                        <n-input v-model:value="editable.billing.country" placeholder="Country" />
                    </template>
                    <template v-else>
                        <p>
                            {{ customer.billing?.address_1 }}<br />
                            {{ customer.billing?.address_2 }}<br />
                            {{ customer.billing?.city }}, {{ customer.billing?.postcode }}<br />
                            {{ customer.billing?.state }}, {{ customer.billing?.country }}
                        </p>
                    </template>
                </div>
                <div class="address-section">
                    <h4>Shipping Address</h4>
                    <template v-if="editing">
                        <n-input v-model:value="editable.shipping.first_name" placeholder="First name" />
                        <n-input v-model:value="editable.shipping.last_name" placeholder="Last name" />
                        <n-input v-model:value="editable.shipping.phone" placeholder="Phone" />
                        <n-input v-model:value="editable.shipping.address_1" placeholder="Address 1" />
                        <n-input v-model:value="editable.shipping.address_2" placeholder="Address 2" />
                        <n-input v-model:value="editable.shipping.city" placeholder="City" />
                        <n-input v-model:value="editable.shipping.state" placeholder="State" />
                        <n-input v-model:value="editable.shipping.postcode" placeholder="Postcode" />
                        <n-input v-model:value="editable.shipping.country" placeholder="Country" />
                    </template>
                    <template v-else>
                        <p>
                            {{ customer.shipping?.first_name }} {{ customer.shipping?.last_name }}<br />
                            <span v-if="customer.shipping?.phone"> {{ customer.shipping?.phone }}<br /></span>
                            {{ customer.shipping?.address_1 }}<br />
                            {{ customer.shipping?.address_2 }}<br />
                            {{ customer.shipping?.city }}, {{ customer.shipping?.postcode }}<br />
                            {{ customer.shipping?.state }}, {{ customer.shipping?.country }}
                        </p>
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
                    <n-button size="medium" @click="enterEdit">Edit</n-button>
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
</script>
