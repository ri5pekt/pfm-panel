<!-- views/reports/ReportView.vue -->
<template>
    <div class="order-view">
        <div class="page-top">
            <n-button @click="router.back()">← Back to Reports</n-button>
            <div class="page-title">{{ reportConfig?.title || "Report" }}</div>
        </div>

        <div class="order-grid">
            <component :is="reportConfig?.component" :report-key="reportKey" @report-created="handleReportCreated" />

            <ReportListPanel ref="listPanel" :report-key="reportKey" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import OrdersToPriorityPanel from "@/components/reports-panels/OrdersToPriorityPanel.vue";
import RefundsPanel from "@/components/reports-panels/RefundsPanel.vue";
import ReportListPanel from "@/components/reports-panels/ReportListPanel.vue";
import TaxesVerificationPanel from "@/components/reports-panels/TaxesVerificationPanel.vue";
import NarvarExportPanel from "@/components/reports-panels/NarvarExportPanel.vue";
import RefundsVerificationPanel from "@/components/reports-panels/RefundsVerificationPanel.vue";
import ReplacementOrdersPanel from "@/components/reports-panels/ReplacementOrdersPanel.vue";
import SaleTaxComparePanel from "@/components/reports-panels/SaleTaxComparePanel.vue";

const route = useRoute();
const router = useRouter();

const reportKey = computed(() => route.params.key);
const listPanel = ref();

const reportMap = {
    "orders-to-priority": {
        title: "Orders Report for Priority",
        component: OrdersToPriorityPanel,
    },
    refunds: {
        title: "Refunds Report",
        component: RefundsPanel,
    },
    "replacement-orders": {
        title: "Replacement Orders Report",
        component: ReplacementOrdersPanel,
    },
    "taxes-verification": {
        title: "Taxes Verification Report",
        component: TaxesVerificationPanel,
    },
    "refunds-verification": {
        title: "Refunds Verification Report",
        component: RefundsVerificationPanel,
    },
    "export-to-narvar": {
        title: "Export Orders to Narvar",
        component: NarvarExportPanel,
    },
    "sale-tax-compare": {
        title: "Sale Tax Compare (file upload)",
        component: SaleTaxComparePanel,
    },
};

const reportConfig = computed(() => reportMap[reportKey.value]);

function handleReportCreated() {
    if (listPanel.value?.loadReports) {
        listPanel.value.loadReports();
    }
}
</script>
