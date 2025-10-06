<template>
    <div class="view-wrapper admin-actions-list">
        <div class="page-title">Admin Activity</div>

        <n-space vertical size="large" class="actions-list-table">
            <n-spin :show="loading">
                <div class="actions-table-scroll">
                    <n-data-table
                        :row-key="rowKey"
                        :columns="columns"
                        :data="rows"
                        :pagination="false"
                        :bordered="true"
                    />
                </div>
            </n-spin>

            <n-pagination
                v-model:page="page"
                v-model:page-size="perPage"
                :page-count="totalPages"
                :page-sizes="ALLOWED_PAGE_SIZES"
                :show-size-picker="true"
                style="margin-top: 1rem"
                :size="isMobile ? 'small' : 'medium'"
                :page-slot="isMobile ? 5 : 7"
            />
        </n-space>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, h } from "vue";
import { NTooltip } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { useMessage } from "naive-ui";
import { useIsMobile } from "@/composables/useIsMobile";
import { request } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";

const router = useRouter();
const route = useRoute();
const message = useMessage();
const { isMobile } = useIsMobile();

const rows = ref([]);
const loading = ref(false);
const totalPages = ref(1);

const PER_PAGE_LS_KEY = "pfm.admin_activity.perPage";
const ALLOWED_PAGE_SIZES = [10, 20, 50, 100];
function coercePerPage(v) {
    const n = Number(v);
    return ALLOWED_PAGE_SIZES.includes(n) ? n : 10;
}
const perPage = ref(coercePerPage(localStorage.getItem(PER_PAGE_LS_KEY)));
watch(perPage, (val) => {
    localStorage.setItem(PER_PAGE_LS_KEY, String(val));
    if (!ALLOWED_PAGE_SIZES.includes(val)) return;
    if (page.value !== 1) {
        router.replace({
            path: "/admin-activity",
            query: { ...route.query, page: 1, per_page: val, search: search.value || undefined },
        });
        page.value = 1;
    } else {
        fetchRows(1);
    }
});

const page = ref(Number(route.query.page || 1));
const search = ref(route.query.search || "");

watch(
    () => [page.value, perPage.value],
    () => {
        router.replace({
            path: "/admin-activity",
            query: { page: page.value, per_page: perPage.value, search: search.value || undefined },
        });
        fetchRows(page.value);
    }
);

function rowKey(row) {
    return row.id;
}

const columns = computed(() => [
    {
        title: "Date",
        key: "created_at",
        render(row) {
            return formatOrderDate(row.created_at);
        },
    },
    { title: "Admin", key: "admin_name" },
    { title: "Action", key: "action_type" },
    { title: "Resource", key: "resource_type" },

    {
        title: "Description",
        key: "description",
        render(row) {
            const full = row.description || "";
            const short = full.length > 30 ? full.slice(0, 30) + "…" : full;

            return h(
                NTooltip,
                { trigger: "hover" },
                {
                    default: () => h("div", full), // popup (will wrap via SCSS)
                    trigger: () =>
                        h(
                            "span",
                            {
                                class: "desc-cell", // optional, style trigger separately
                            },
                            short
                        ),
                }
            );
        },
    },
]);

async function fetchRows(currentPage = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append("per_page", perPage.value);
        params.append("page", currentPage);
        if (search.value) params.append("search", search.value);

        const res = await request({
            url: `/admin-actions?${params}`,
            raw: true,
        });
        const data = await res.json();
        rows.value = data || [];
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1", 10);
    } catch (e) {
        console.error(e);
        message.error("Failed to load admin actions");
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchRows(page.value);
});
</script>
