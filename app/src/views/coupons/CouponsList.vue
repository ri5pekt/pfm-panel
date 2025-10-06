<template>
    <div class="view-wrapper coupons-list">
        <div class="page-title">Coupons</div>

        <n-space class="filters" align="end" wrap>
            <!-- Coupon category (immediate filter) -->
            <div class="filter-field">
                <n-text depth="3">Category</n-text>
                <n-select
                    v-model:value="filters.coupon_category"
                    :options="categoryOptions"
                    placeholder="Select category"
                    clearable
                    style="width: 220px"
                    :loading="categoriesLoading"
                    @update:value="onCategoryChange"
                />
            </div>

            <!-- Code search (manual trigger) -->
            <div class="filter-field">
                <n-text depth="3">Coupon Code</n-text>
                <div class="row" style="display: flex; gap: 6px">
                    <n-input
                        v-model:value="filters.search_value"
                        placeholder="Enter code"
                        style="width: 220px"
                        @keyup.enter="emitSearch"
                    />
                    <n-button size="small" type="primary" @click="emitSearch">Search</n-button>
                </div>
            </div>

            <n-button tertiary size="medium" type="default" @click="resetFilters">Reset All</n-button>
        </n-space>

        <n-spin :show="loading">
            <n-data-table :columns="columns" :data="coupons" :pagination="false" :bordered="true" />
        </n-spin>

        <n-pagination v-model:page="page" :page-count="totalPages" :page-size="perPage" style="margin-top: 1rem" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";

const router = useRouter();
const route = useRoute();

const coupons = ref([]);
const loading = ref(false);
const totalPages = ref(1);
const perPage = 10;

const page = ref(parseInt(route.query.page || "1"));

// Filters in URL (like Customers)
const filters = ref({
    coupon_category: route.query.coupon_category ?? null,
    search_value: route.query.search_value ?? null,
});

// Static for now; later load from server
const categoryOptions = ref([
    { label: "All categories", value: null }, // always present
]);
const categoriesLoading = ref(false);

async function loadCategories() {
    categoriesLoading.value = true;
    try {
        const res = await request({ url: `/coupons/categories?hide_empty=true`, raw: true });
        const rows = await res.json();
        const dynamic = rows.map((t) => ({
            label: `${t.name} (${t.count})`,
            value: t.slug,
        }));
        // Keep user's current selection if still valid
        const current = filters.value.coupon_category;
        categoryOptions.value = [{ label: "All categories", value: null }, ...dynamic];

        // If current category no longer exists, clear it
        if (current && !dynamic.some((o) => o.value === current)) {
            filters.value.coupon_category = null;
        }
    } catch (e) {
        console.warn("Coupon categories load failed, keeping static:", e);
        // fallback: keep only "All categories"
    } finally {
        categoriesLoading.value = false;
    }
}

// Columns for table
const columns = computed(() => [
    { title: "Code", key: "code" },
    { title: "Coupon type", key: "discount_type" },
    { title: "Coupon amount", key: "amount" },
    { title: "Usage / Limit", key: "usage_summary" },
    { title: "Expiry date", key: "expiry_date" },
    { title: "Parent category", key: "parent_categories_display" },
    { title: "Coupon categories", key: "categories_display" },
]);

// Keep URL in sync (page + filters)
watch([page, () => filters.value.coupon_category, () => filters.value.search_value], () => {
    const q = {
        page: page.value,
    };
    if (filters.value.coupon_category) q.coupon_category = filters.value.coupon_category;
    if (filters.value.search_value) q.search_value = filters.value.search_value;
    router.replace({ name: "coupons", query: q });
});

// Re-fetch when page changes or category changes
watch([page, () => filters.value.coupon_category], () => {
    fetchCoupons();
});

// Category triggers immediately
function onCategoryChange() {
    page.value = 1;
    fetchCoupons();
}

// Manual search by code
function emitSearch() {
    page.value = 1;
    fetchCoupons();
}

function resetFilters() {
    filters.value.coupon_category = null;
    filters.value.search_value = null;
    page.value = 1;
    fetchCoupons();
}

async function fetchCoupons() {
    loading.value = true;
    try {
        const payload = {
            page: page.value,
            per_page: perPage,
            // server expects 'search' (for code) to keep it generic
            search: filters.value.search_value || "",
            coupon_category: filters.value.coupon_category || "",
        };
        const params = new URLSearchParams(
            Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== "" && v !== null))
        ).toString();

        const res = await request({ url: `/coupons?${params}`, raw: true });
        const rows = await res.json();

        // Slight presentation massage (usage summary + categories display)
        coupons.value = rows.map((r) => ({
            ...r,
            usage_summary: r.usage_limit ? `${r.usage_count}/${r.usage_limit}` : `${r.usage_count} / ∞`,
            categories_display: (r.categories || []).join(", "),
            parent_categories_display: (r.parent_categories || []).join(", "), // ✅ NEW
        }));

        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");
    } catch (e) {
        // handle as you like (toast, etc.)
        console.error(e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadCategories();
    fetchCoupons();
});
</script>
