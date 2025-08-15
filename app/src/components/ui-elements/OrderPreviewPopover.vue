<!-- OrderPreviewPopover.vue -->
<template>
    <n-popover trigger="hover" placement="right-start" :delay="400" :show-arrow="true" @update:show="onShow">
        <template #trigger>
            <slot />
        </template>

        <div style="min-width: 280px; min-height: 100px" class="order-popover">
            <div v-if="loading" style="min-height: 100px" class="loader">
                <n-spin size="large" />
            </div>

            <div v-else-if="error" class="error">⚠️ {{ error }}</div>

            <div v-else-if="products.length === 0" class="no-products">No products</div>

            <div v-else>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in products" :key="item.id">
                            <td>
                                <div class="product-cell">
                                    <img :src="item.image" alt="Product" class="product-image" />
                                    <p class="title">{{ item.name }}</p>
                                </div>
                            </td>
                            <td class="quantity">{{ item.quantity }}</td>
                            <td class="price">
                                {{ formatCurrency(item.total_raw, item.currency) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </n-popover>
</template>

<script setup>
import { ref, watch } from "vue";
import { request } from "@/utils/api";
import { formatCurrency } from "@/utils/utils";

const props = defineProps({
    orderId: { type: Number, required: true },
    refreshKey: { type: Number, default: 0 }, // 👈 new
});

const loading = ref(false);
const error = ref(null);
const products = ref([]);

// super tiny cache per popover instance
// Map<number, { items: any[] }>
const cache = new Map();

watch(
    () => props.refreshKey,
    () => {
        cache.clear(); // 👈 list changed => drop stale previews
    }
);

async function onShow(visible) {
    if (!visible) return;

    // hit cache first
    const hit = cache.get(props.orderId);
    if (hit) {
        products.value = hit.items;
        error.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    error.value = null;
    products.value = [];

    try {
        const res = await request({ url: `/orders/${props.orderId}/preview` });
        const items = res.items ?? [];
        cache.set(props.orderId, { items });
        products.value = items;
    } catch (err) {
        error.value = "Failed to load order preview";
        console.error(err);
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
table th {
    font-weight: 600;
    padding-bottom: 4px;
}
</style>
