<template>
    <div class="order-view">
        <div class="page-title">Order #{{ props.id }}</div>
        <n-button @click="router.back()">← Back to Orders</n-button>

        <div class="order-grid">
            <!-- Order Info Panel -->
            <div class="panel order-info-panel">
                <h3>Order Info</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else>
                    <p><strong>ID:</strong> {{ order.id }}</p>

                    <p>
                        <strong>Status: </strong>
                        <template v-if="editingOrderInfo">
                            <n-select v-model:value="editableOrderInfo.status" :options="statusOptions" placeholder="Select status" style="width: 200px" />
                        </template>
                        <template v-else>
                            {{ order.status }}
                        </template>
                    </p>

                    <p><strong>Date Created:</strong> {{ formattedCreatedDate }}</p>
                    <p>
                        <strong>New/Returning: </strong>
                        <template v-if="editingOrderInfo">
                            <n-select v-model:value="editableOrderInfo.newOrReturning" :options="newOrReturningOptions" placeholder="Select type" style="width: 200px" />
                        </template>
                        <template v-else>
                            {{ newOrReturning }}
                        </template>
                    </p>

                    <div style="margin-top: 1rem">
                        <template v-if="editingOrderInfo">
                            <n-button size="small" type="primary" :loading="savingOrderInfo" @click="saveOrderInfo"> Save </n-button>
                            <n-button size="small" ghost style="margin-left: 0.5rem" @click="cancelEditOrderInfo" :disabled="savingOrderInfo"> Cancel </n-button>
                        </template>
                        <template v-else>
                            <n-button size="small" @click="enterEditOrderInfo">Edit</n-button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Customer Info Panel -->
            <div class="panel info-panel">
                <h3>Customer Info</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else>
                    <div style="margin-bottom: 1rem">
                        <p>
                            <strong>Name: </strong>
                            <template v-if="editingCustomerInfo">
                                <n-input v-model:value="editableOrderInfo.billing.first_name" placeholder="First name" style="width: 130px; margin-right: 8px" />
                                <n-input v-model:value="editableOrderInfo.billing.last_name" placeholder="Last name" style="width: 130px" />
                            </template>
                            <template v-else> {{ order.billing?.first_name }} {{ order.billing?.last_name }} </template>
                        </p>

                        <p>
                            <strong>Email: </strong>
                            <template v-if="editingCustomerInfo">
                                <n-input v-model:value="editableOrderInfo.billing.email" type="email" placeholder="Email" style="width: 270px" />
                            </template>
                            <template v-else>
                                {{ order.billing?.email }}
                            </template>
                        </p>

                        <p>
                            <strong>Phone: </strong>
                            <template v-if="editingCustomerInfo">
                                <n-input v-model:value="editableOrderInfo.billing.phone" placeholder="Phone" style="width: 200px" />
                            </template>
                            <template v-else>
                                {{ order.billing?.phone }}
                            </template>
                        </p>
                    </div>

                    <!-- Address Columns -->
                    <div class="address-columns">
                        <!-- Billing -->
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

                        <!-- Shipping -->
                        <div class="address-section">
                            <h4>Shipping Address</h4>
                            <template v-if="editingCustomerInfo">
                                <n-input v-model:value="editableOrderInfo.shipping.first_name" placeholder="First name" />
                                <n-input v-model:value="editableOrderInfo.shipping.last_name" placeholder="Last name" />
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
                                    {{ order.shipping?.address_1 }}<br />
                                    {{ order.shipping?.address_2 }}<br />
                                    {{ order.shipping?.city }}, {{ order.shipping?.postcode }}<br />
                                    {{ order.shipping?.state }}, {{ order.shipping?.country }}
                                </p>
                            </template>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div style="margin-top: 1rem">
                        <template v-if="editingCustomerInfo">
                            <n-button size="small" type="primary" @click="saveCustomerInfo" :loading="savingCustomerInfo">Save</n-button>
                            <n-button size="small" ghost style="margin-left: 0.5rem" @click="cancelEditCustomerInfo" :disabled="savingCustomerInfo">Cancel</n-button>
                        </template>
                        <template v-else>
                            <n-button size="small" @click="enterEditCustomerInfo">Edit</n-button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Order Products Panel -->
            <div class="panel products-panel">
                <h3>Products</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <table v-else class="product-table">
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
                            <td>
                                <div class="product-cell">
                                    <img :src="item.image?.src || 'https://via.placeholder.com/48'" alt="Product image" class="product-image" />
                                    <div>
                                        <strong>{{ item.name }}</strong
                                        ><br />
                                        <small v-if="item.sku">SKU: {{ item.sku }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ formatCurrency(item.subtotal / item.quantity) }}</td>
                            <td>
                                {{ item.quantity }}
                                <div v-if="item.qty_refunded > 0" class="refunded-qty">↩ -{{ item.qty_refunded }}</div>
                            </td>
                            <td>
                                {{ formatCurrency(item.total) }}
                                <div v-if="parseFloat(item.subtotal) > parseFloat(item.total)" class="line-discount">{{ formatCurrency(item.subtotal - item.total) }} discount</div>
                                <div v-if="item.total_refunded > 0" class="refunded-amount">↩ -{{ formatCurrency(item.total_refunded) }}</div>
                            </td>
                            <td>{{ formatCurrency(+item.total_tax) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Warehouse Export Panel -->
            <div class="panel warehouse-export-panel">
                <h3>Warehouse Export</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else>
                    <p><strong>Warehouse:</strong> {{ getMeta("warehouse_to_export") || "—" }}</p>
                    <p><strong>Export Status:</strong> {{ getMeta("warehouse_export_status") || "—" }}</p>
                    <p><strong>Address Validated:</strong> {{ getMeta("validate_address_status") || "—" }}</p>
                    <p v-if="trackingNumber"><strong>Tracking Number:</strong> {{ trackingNumber }}</p>
                </div>
            </div>

            <!-- Order Totals Panel -->
            <div class="panel totals-panel">
                <h3>Order Totals</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else class="totals-list">
                    <!-- Subtotal -->
                    <div class="totals-section">
                        <p><strong>Items Subtotal:</strong> {{ formatCurrency(subtotal) }}</p>
                    </div>

                    <!-- Discounts -->
                    <div v-if="order.coupon_lines?.length" class="totals-section">
                        <p><strong>Discounts:</strong></p>
                        <ul class="breakdown-list coupon-list">
                            <li v-for="coupon in order.coupon_lines" :key="coupon.id">
                                <span class="coupon-code">{{ coupon.code }}</span>
                                — -{{ formatCurrency(coupon.discount) }}
                            </li>
                        </ul>
                    </div>

                    <!-- Fees -->
                    <div v-if="order.fee_lines?.length" class="totals-section">
                        <p><strong>Fees:</strong></p>
                        <ul class="breakdown-list">
                            <li v-for="fee in order.fee_lines" :key="fee.id">{{ fee.name }} — {{ formatCurrency(fee.total) }}</li>
                        </ul>
                    </div>

                    <!-- Shipping -->
                    <div class="totals-section">
                        <p><strong>Shipping:</strong> {{ formatCurrency(order.shipping_total) }} ({{ shippingMethodTitle }})</p>
                    </div>

                    <!-- Taxes -->
                    <div v-if="order.tax_lines?.length" class="totals-section">
                        <p><strong>Taxes:</strong></p>
                        <ul class="breakdown-list">
                            <li v-for="tax in order.tax_lines" :key="tax.id">{{ tax.rate_code }} — {{ formatCurrency(+tax.tax_total) }}</li>
                        </ul>
                    </div>

                    <!-- Total -->
                    <hr class="totals-divider" />
                    <div class="totals-section final-totals">
                        <div v-if="totalRefunded > 0" class="totals-section refunded-row">
                            <p><strong>Refunded:</strong> ↩ -{{ formatCurrency(totalRefunded) }}</p>
                        </div>
                        <p><strong>Order Total:</strong> {{ formatCurrency(order.total) }}</p>
                        <p><strong>Paid:</strong> {{ formatCurrency(order.total) }}</p>
                        <p><strong>Payment Method:</strong> {{ order.payment_method_title }}</p>
                    </div>
                </div>
            </div>

            <!-- Order Notes Panel -->
            <div class="panel notes-panel">
                <h3>Order Notes</h3>
                <n-skeleton v-if="loadingNotes" text :repeat="3" />
                <div v-else class="notes-scroll">
                    <div v-for="note in orderNotes" :key="note.id" class="note">
                        <div class="note-text" v-html="note.note"></div>
                        <p class="note-meta">
                            <abbr :title="note.date_created" class="exact-date">
                                {{ new Date(note.date_created).toLocaleString() }}
                            </abbr>
                            <span v-if="note.author"> — {{ note.author }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Past Orders Panel -->
            <div class="panel past-orders-panel span-2-cols">
                <h3>Past Orders</h3>
                <div class="past-orders-scroll">
                    <n-data-table :columns="pastOrdersColumns" :data="pastOrders" :loading="pastOrdersLoading" size="small" striped />

                    <div class="load-more-wrapper" v-if="pastOrdersHasMore">
                        <n-button @click="loadMorePastOrders" :loading="pastOrdersLoading" size="small"> Load More </n-button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, h } from "vue";
import { useRoute, useRouter } from "vue-router";
import { apiBase, apiBaseCustom, authHeader } from "@/utils/api";
import { formatOrderDate, formatCurrency, setCurrency } from "@/utils/utils";
import { useMessage } from "naive-ui";

const message = useMessage();
const route = useRoute();
const router = useRouter();

const order = ref({});

const orderNotes = ref([]);
const loadingOrder = ref(true);
const loadingNotes = ref(true);

const editingOrderInfo = ref(false);
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
        address_1: "",
        address_2: "",
        city: "",
        state: "",
        postcode: "",
        country: "",
    },
});

const savingOrderInfo = ref(false);

const editingCustomerInfo = ref(false);
const savingCustomerInfo = ref(false);

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

const newOrReturning = computed(() => {
    const meta = order.value?.meta_data || [];
    const field = meta.find((m) => m.key === "new_or_returning");
    return field?.value || "—";
});

const subtotal = computed(() => {
    if (!order.value?.line_items) return "—";
    const sum = order.value.line_items.reduce((total, item) => {
        return total + parseFloat(item.subtotal || 0);
    }, 0);
    return sum.toFixed(2);
});

// Helper to fetch meta
function getMeta(key) {
    const meta = order.value?.meta_data?.find((m) => m.key === key);
    return meta?.value;
}

// Shipping method title
const shippingMethodTitle = computed(() => {
    const methods = order.value?.shipping_lines || [];
    return methods.length ? methods[0].method_title : "—";
});

// Tracking number logic
const trackingNumber = computed(() => {
    const meta = getMeta("_wc_shipment_tracking_items");
    return Array.isArray(meta) && meta.length ? meta[0].tracking_number : null;
});

const totalRefunded = computed(() => {
    if (!order.value?.refunds?.length) return 0;
    return order.value.refunds.reduce((sum, r) => sum + Math.abs(parseFloat(r.total || 0)), 0);
});

const formattedCreatedDate = computed(() => {
    const raw = order.value?.date_created.date || "";
    return formatOrderDate(raw, true);
});

const props = defineProps({
    id: String,
});

const fetchOrder = async () => {
    loadingOrder.value = true;
    order.value = {};
    try {
        const res = await fetch(`${apiBaseCustom}/orders/${props.id}`, {
            headers: { Authorization: authHeader },
        });
        order.value = await res.json();
        console.log("Fetched order object 🕵️:", order.value);
        console.log("order.value.line_items", order.value.line_items);
    } finally {
        loadingOrder.value = false;
    }
};

const fetchNotes = async () => {
    loadingNotes.value = true;
    orderNotes.value = [];
    try {
        const res = await fetch(`${apiBase}/orders/${props.id}/notes`, {
            headers: { Authorization: authHeader },
        });
        const notes = await res.json();
        orderNotes.value = notes;
    } finally {
        loadingNotes.value = false;
    }
};

function enterEditOrderInfo() {
    editableOrderInfo.value.status = order.value.status;
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
        const res = await fetch(`${apiBaseCustom}/orders/${props.id}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Authorization: authHeader,
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) throw new Error("Failed to update");

        message.success("Order updated");
        editingOrderInfo.value = false;
        await loadOrderData(); // wait for fresh data
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
    };

    try {
        const res = await fetch(`${apiBaseCustom}/orders/${props.id}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Authorization: authHeader,
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) throw new Error("Failed to update");

        message.success("Customer info updated");
        editingCustomerInfo.value = false;
        await loadOrderData();
    } catch (err) {
        message.error("Update failed");
        console.error(err);
    } finally {
        savingCustomerInfo.value = false;
    }
}

const loadOrderData = () => {
    fetchOrder().then(fetchPastOrders);
    fetchNotes();
};

onMounted(() => {
    loadOrderData();
});

// Reload on route param change
watch(
    () => props.id,
    () => {
        loadOrderData();
    }
);
watch(
    () => order.value.currency,
    (currency) => {
        setCurrency(currency);
    },
    { immediate: true }
);

const billingKeys = ["first_name", "last_name", "email", "phone", "address_1", "address_2", "city", "state", "postcode", "country"];

const shippingKeys = ["first_name", "last_name", "address_1", "address_2", "city", "state", "postcode", "country"];

const pastOrdersColumns = [
    {
        title: "Order ID",
        key: "id",
        render(row) {
            return h("a", { href: `#/orders/${row.id}` }, `#${row.id}`);
        },
    },
    {
        title: "Date",
        key: "date_created",
        render(row) {
            return formatOrderDate(row.date_created);
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            return row.status.charAt(0).toUpperCase() + row.status.slice(1);
        },
    },
    {
        title: "Total",
        key: "total",
        render(row) {
            return formatCurrency(row.total);
        },
    },
];

const pastOrders = ref([]);
const pastOrdersPage = ref(1);
const pastOrdersHasMore = ref(true);
const pastOrdersLoading = ref(false);

const fetchPastOrders = async () => {
    if (!order.value?.customer_id) return;

    pastOrdersLoading.value = true;

    try {
        const res = await fetch(`${apiBaseCustom}/orders/by-user/${order.value.customer_id}?page=${pastOrdersPage.value}&per_page=10`, { headers: { Authorization: authHeader } });
        const data = await res.json();

        // Remove current order and append new ones
        const filtered = data.filter((o) => o.id !== parseInt(props.id));
        if (filtered.length < 10) pastOrdersHasMore.value = false;

        pastOrders.value.push(...filtered);
    } catch (err) {
        console.error("Failed to load past orders:", err);
    } finally {
        pastOrdersLoading.value = false;
    }
};

const loadMorePastOrders = () => {
    pastOrdersPage.value += 1;
    fetchPastOrders();
};

watch(
    () => order.value,
    (val) => {
        editableOrderInfo.value.status = val.status;
        editableOrderInfo.value.newOrReturning = getMeta("new_or_returning") || "";

        billingKeys.forEach((key) => {
            editableOrderInfo.value.billing[key] = val.billing?.[key] || "";
        });

        shippingKeys.forEach((key) => {
            editableOrderInfo.value.shipping[key] = val.shipping?.[key] || "";
        });
    }
);
</script>
