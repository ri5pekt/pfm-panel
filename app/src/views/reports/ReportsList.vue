<!-- ReportsList.vue -->
<template>
    <div class="view-wrapper reports-list">
        <div class="page-title">Available Reports</div>

        <div class="reports-grid">
            <div class="panel" v-for="report in reports" :key="report.key">
                <h3>{{ report.title }}</h3>
                <p>{{ report.description }}</p>
                <n-button type="primary" size="small" style="margin-top: 1rem" @click="goToReport(report.key)">
                    Create Report
                </n-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from "vue-router";

const router = useRouter();

const reports = [
    {
        key: "orders-to-priority",
        title: "Orders Report for Priority",
        description: "Generates a Priority-compatible export of selected orders.",
    },
    {
        key: "refunds",
        title: "Refunds Report",
        description: "Detailed breakdown of all refunds processed in the system.",
    },
    {
        key: "replacement-orders",
        title: "Replacement Orders Report",
        description: "CSV export of replacement orders filtered by product, reason, and warehouse.",
    },
    {
        key: "taxes-verification",
        title: "Taxes Verification Report",
        description: "Cross-check WooCommerce order totals and tax for verification. CSV export.",
    },
    {
        key: "refunds-verification",
        title: "Refunds Verification Report",
        description: "Cross-check Woo refunds vs Braintree credits and Complyt refund transactions. CSV export.",
    },
    {
        key: "export-to-narvar",
        title: "Export Orders to Narvar",
        description: "Generate a Narvar-ready export by date range or specific order IDs.",
    },
    {
        key: "sale-tax-compare",
        title: "Sale Tax Compare (file upload)",
        description: "Upload a CSV file to compare WooCommerce and Braintree tax calculations. Output includes added tax columns.",
    },
];

function goToReport(key) {
    router.push({ name: "report-view", params: { key } });
}
</script>
