<template>
    <div class="bulk-edit-panel" style="margin-bottom: 1rem; display: flex; gap: 12px; align-items: flex-end">
        <n-select
            v-model:value="selectedAction"
            :options="actionOptions"
            placeholder="Bulk actions"
            style="width: 200px"
        />

        <n-select
            v-if="selectedAction === 'change_status'"
            v-model:value="statusToSet"
            :options="statusOptions"
            placeholder="Select new status"
            style="width: 200px"
        />

        <n-select
            v-if="selectedAction === 'export_to_warehouse'"
            v-model:value="warehouseToExport"
            :options="warehouseOptions"
            placeholder="Select warehouse"
            style="width: 200px"
        />

        <n-button
            type="primary"
            :disabled="!canApply || isApplying"
            :loading="isApplying"
            @click="showConfirmModal = true"
        >
            Apply
        </n-button>
    </div>

    <!-- BULK CONFIRMATION MODAL -->
    <n-modal v-model:show="showConfirmModal" preset="dialog" type="warning" title="Confirm Bulk Action">
        <template #default>
            Apply "<strong>{{ selectedAction }}</strong
            >" to {{ selectedOrders.length }} orders?
            <br />
            This will affect order data permanently.
        </template>
        <template #action>
            <n-button ghost @click="showConfirmModal = false">Cancel</n-button>
            <n-button type="primary" :loading="isApplying" @click="applyBulk">Apply</n-button>
        </template>
    </n-modal>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useMessage } from "naive-ui";
import { request } from "@/utils/api";

const props = defineProps({
    selectedOrders: Array,
    onComplete: Function,
});

const emit = defineEmits(["toggle-checkboxes"]);

const selectedAction = ref(null);
const statusToSet = ref(null);
const isApplying = ref(false);
const showConfirmModal = ref(false);

const message = useMessage();

const actionOptions = [
    { label: "Bulk Actions", value: null },
    { label: "Change Status", value: "change_status" },
    { label: "Export to Warehouse", value: "export_to_warehouse" },
];

const statusOptions = [
    { label: "Pending", value: "pending" },
    { label: "Processing", value: "processing" },
    { label: "On Hold", value: "on-hold" },
    { label: "Completed", value: "completed" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Refunded", value: "refunded" },
    { label: "Failed", value: "failed" },
];

const warehouseToExport = ref(null);
const warehouseOptions = [
    { label: "Decide based on rules", value: "decide_based_on_rules" },
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
];

const canApply = computed(() => {
    if (!props.selectedOrders?.length) return false;
    if (selectedAction.value === "change_status" && !statusToSet.value) return false;
    if (selectedAction.value === "export_to_warehouse" && !warehouseToExport.value) return false;
    return true;
});

watch(selectedAction, (val) => {
    if (!val) {
        statusToSet.value = null;
        emit("toggle-checkboxes", false);
    } else {
        emit("toggle-checkboxes", true);
    }
});

async function applyBulk() {
    showConfirmModal.value = false; // 🧼 close modal FIRST, before waiting
    isApplying.value = true;

    try {
        const payload = {
            ids: props.selectedOrders,
            action: selectedAction.value,
            value: selectedAction.value === "change_status" ? statusToSet.value : warehouseToExport.value,
        };

        await request({
            url: "/orders/bulk",
            method: "POST",
            body: payload,
        });

        message.success("Bulk action complete!");
        props.onComplete?.();
    } catch (err) {
        console.error("Bulk update failed:", err);
        message.error("Bulk update failed");
    } finally {
        isApplying.value = false;
        selectedAction.value = null;
        statusToSet.value = null;
        emit("toggle-checkboxes", false);
    }
}
</script>
