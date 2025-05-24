<template>
    <div class="order-view">
        <div class="page-title">Order #{{ props.id }}</div>
        <n-button @click="router.back()">← Back to Orders</n-button>

        <div class="order-grid">
            <!-- Order Info Panel -->
            <div class="panel order-info-panel">
                <h3>Order Info</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="1" />
                <div v-else>
                    <p>
                        <strong>Created At:</strong>
                        {{ formattedCreatedDate }}
                    </p>
                    <p><strong>New/Returning:</strong> {{ newOrReturning }}</p>
                </div>
            </div>
            <!-- Customer Info Panel -->
            <div class="panel info-panel">
                <h3>Customer Info</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else>
                    <p><strong>Name:</strong> {{ order.billing?.first_name }} {{ order.billing?.last_name }}</p>
                    <p><strong>Email:</strong> {{ order.billing?.email }}</p>
                    <p><strong>Phone:</strong> {{ order.billing?.phone }}</p>
                    <p>
                        <strong>Address:</strong><br />
                        {{ order.billing?.address_1 }}<br />
                        {{ order.billing?.city }}, {{ order.billing?.postcode }}<br />
                        {{ order.billing?.country }}
                    </p>
                </div>
            </div>

            <!-- Order Products Panel -->
            <div class="panel products-panel">
                <h3>Products</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="3" />
                <table v-else class="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Cost</th>
                            <th>Qty</th>
                            <th>Total</th>
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
                            <td>${{ (item.subtotal / item.quantity).toFixed(2) }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>${{ Number(item.total).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
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

            <!-- Order Totals Panel -->
            <div class="panel totals-panel">
                <h3>Order Totals</h3>
                <n-skeleton v-if="loadingOrder" text :repeat="5" />
                <div v-else class="totals-list">
                    <!-- Subtotal -->
                    <p><strong>Items Subtotal:</strong> ${{ subtotal }}</p>

                    <!-- Fees -->
                    <div v-if="order.fee_lines?.length">
                        <p><strong>Fees:</strong></p>
                        <ul class="breakdown-list">
                            <li v-for="fee in order.fee_lines" :key="fee.id">{{ fee.name }} — ${{ fee.total }}</li>
                        </ul>
                    </div>

                    <!-- Shipping -->
                    <p><strong>Shipping:</strong> ${{ order.shipping_total || "0.00" }}</p>

                    <!-- Taxes -->
                    <div v-if="order.tax_lines?.length">
                        <p><strong>Taxes:</strong></p>
                        <ul class="breakdown-list">
                            <li v-for="tax in order.tax_lines" :key="tax.id">{{ tax.rate_code }} — ${{ tax.total }}</li>
                        </ul>
                    </div>

                    <!-- Totals -->
                    <p>
                        <strong><u>Order Total:</u></strong> ${{ order.total }}
                    </p>
                    <p><strong>Paid:</strong> ${{ order.total }}</p>
                    <p><strong>Payment Method:</strong> {{ order.payment_method_title }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { apiBase,apiBaseCustom, authHeader } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";

const route = useRoute();
const router = useRouter();

const order = ref({});

const orderNotes = ref([]);
const loadingOrder = ref(true);
const loadingNotes = ref(true);

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

const loadOrderData = () => {
    fetchOrder();
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
</script>
