<template>
    <div class="panel">
        <h3>Replacement Orders Report</h3>
        <p>
            Generate a CSV export of replacement orders filtered by date, products, replacement reason, and warehouse.
        </p>

        <n-space vertical size="large">
            <div>
                <label>Date Range:</label>
                <n-date-picker v-model:value="dateRange" type="daterange" format="yyyy-MM-dd" style="width: 100%" />
            </div>

            <div>
                <label>Products:</label>
                <n-select
                    v-model:value="selectedProductIds"
                    :options="productOptions"
                    :loading="productLoading"
                    multiple
                    filterable
                    clearable
                    tag
                    placeholder="Optional – select products"
                    style="width: 100%"
                    :render-label="(opt) => (opt.renderLabel ? opt.renderLabel() : opt.label)"
                    @focus="ensureProductsLoaded"
                    @update:show="(open) => open && ensureProductsLoaded()"
                />
            </div>

            <div>
                <label>Replacement Reason:</label>
                <n-select
                    v-model:value="selectedReasons"
                    :options="reasonOptions"
                    :loading="reasonLoading"
                    multiple
                    filterable
                    clearable
                    tag
                    placeholder="Optional – select or type reasons"
                    style="width: 100%"
                    @focus="ensureReasonsLoaded"
                    @update:show="(open) => open && ensureReasonsLoaded()"
                />
            </div>

            <div>
                <label>Warehouse:</label>
                <n-select
                    v-model:value="selectedWarehouse"
                    :options="warehouseOptions"
                    placeholder="All warehouses"
                    clearable
                    style="width: 100%"
                />
            </div>

            <div>
                <label>Created By:</label>
                <n-select
                    v-model:value="selectedCreators"
                    :options="creatorOptions"
                    :loading="creatorLoading"
                    multiple
                    filterable
                    clearable
                    placeholder="Optional – select creators"
                    style="width: 100%"
                    @focus="ensureCreatorsLoaded"
                    @update:show="(open) => open && ensureCreatorsLoaded()"
                />
            </div>

            <n-progress
                v-if="progressPercent > 0"
                type="line"
                :percentage="progressPercent"
                :status="progressPercent === 100 ? 'success' : 'info'"
                indicator-placement="inside"
                processing
            />

            <n-button type="primary" :loading="loading" @click="startReport">Generate CSV Report</n-button>
        </n-space>

        <n-modal v-model:show="showModal" preset="dialog" title="Replacement Orders Report Ready">
            <template #default> Your replacement orders report is ready to download. </template>
            <template #action>
                <a
                    :href="downloadUrl"
                    :download="downloadUrl.split('/').pop()"
                    target="_blank"
                    @click="showModal = false"
                >
                    <n-button type="primary">Download CSV</n-button>
                </a>
            </template>
        </n-modal>
    </div>
</template>

<script setup>
import { ref, h } from "vue";
import { request } from "@/utils/api";
import { useReportChunks } from "@/composables/useReportChunks";

const emit = defineEmits(["report-created"]);

const dateRange = ref(null);
const selectedProductIds = ref([]);
const selectedReasons = ref([]);
const selectedWarehouse = ref(null);
const selectedCreators = ref([]);

const productOptions = ref([]);
const productLoading = ref(false);
let productsLoaded = false;

const reasonOptions = ref([]);
const reasonLoading = ref(false);
let reasonsLoaded = false;

const creatorOptions = ref([]);
const creatorLoading = ref(false);
let creatorsLoaded = false;

const warehouseOptions = [
    { label: "All warehouses", value: null },
    { label: "Shipstation", value: "shipstation" },
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
];

function formatDate(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

const {
    loading,
    progress: progressPercent,
    showModal,
    downloadUrl,
    runReport,
} = useReportChunks("replacement-orders", 300);

async function ensureProductsLoaded() {
    if (productsLoaded) return;
    productLoading.value = true;
    try {
        const res = await request({ url: "/products-by-category" });
        productOptions.value = (res || []).map((group) => ({
            type: "group",
            label: group.label,
            key: group.key,
            children: (group.products || []).map((p) => ({
                label: p.name,
                value: p.id,
                sku: p.sku,
                id: p.id,
                renderLabel: () =>
                    h("div", { style: "display:flex;align-items:center;gap:8px" }, [
                        p.image
                            ? h("img", {
                                  src: p.image,
                                  style: "width:18px;height:18px;object-fit:cover;border-radius:2px",
                              })
                            : null,
                        h("span", `${p.name}`),
                    ]),
            })),
        }));
        productsLoaded = true;
    } catch (e) {
        console.error("Failed to load products", e);
        window.$message?.error("Failed to load products list");
    } finally {
        productLoading.value = false;
    }
}

async function ensureReasonsLoaded() {
    if (reasonsLoaded) return;
    reasonLoading.value = true;
    try {
        const res = await request({ url: "/replacements/reasons" });
        reasonOptions.value = (res || []).map((reason) => ({
            label: reason,
            value: reason,
        }));
        reasonsLoaded = true;
    } catch (e) {
        console.error("Failed to load replacement reasons", e);
        window.$message?.error("Failed to load replacement reasons");
    } finally {
        reasonLoading.value = false;
    }
}

async function ensureCreatorsLoaded() {
    if (creatorsLoaded) return;
    creatorLoading.value = true;
    try {
        const res = await request({ url: "/replacements/creators" });
        creatorOptions.value = (res || []).map((user) => ({
            label: user.name ? `${user.name}${user.email ? ` (${user.email})` : ""}` : user.email || `User #${user.id}`,
            value: user.id,
        }));
        creatorsLoaded = true;
    } catch (e) {
        console.error("Failed to load creators", e);
        window.$message?.error("Failed to load creators list");
    } finally {
        creatorLoading.value = false;
    }
}

async function startReport() {
    const hasRange = Array.isArray(dateRange.value) && dateRange.value.length === 2;

    if (!hasRange) {
        window.$message?.error("Please select a date range.");
        return;
    }

    const payload = {
        date_from: formatDate(dateRange.value[0]),
        date_to: formatDate(dateRange.value[1]),
        extension: "csv",
    };

    if (selectedProductIds.value.length > 0) {
        payload.product_ids = selectedProductIds.value.map((id) => Number(id));
    }

    if (selectedReasons.value.length > 0) {
        payload.replacement_reasons = selectedReasons.value;
    }

    if (selectedWarehouse.value) {
        payload.warehouse = selectedWarehouse.value;
    }

    if (selectedCreators.value.length > 0) {
        payload.created_by = selectedCreators.value.map((id) => Number(id));
    }

    await runReport(payload);

    if (downloadUrl.value) {
        emit("report-created");
    }
}
</script>
