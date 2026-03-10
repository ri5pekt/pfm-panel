<template>
    <div class="panel">
        <h3>Admin Activity Report</h3>
        <p>Generate a CSV export of admin activity filtered by date range, action type, resource type, and admin name.</p>

        <n-space vertical size="large">
            <div>
                <label>Date Range:</label>
                <n-date-picker v-model:value="dateRange" type="daterange" format="yyyy-MM-dd" style="width: 100%" />
            </div>

            <div>
                <label>Action:</label>
                <n-select
                    v-model:value="selectedAction"
                    :options="actionOptions"
                    :loading="actionLoading"
                    placeholder="All actions"
                    clearable
                    filterable
                    style="width: 100%"
                    @focus="ensureActionsLoaded"
                    @update:show="(open) => open && ensureActionsLoaded()"
                />
            </div>

            <div>
                <label>Resource:</label>
                <n-select
                    v-model:value="selectedResource"
                    :options="resourceOptions"
                    :loading="resourceLoading"
                    placeholder="All resources"
                    clearable
                    filterable
                    style="width: 100%"
                    @focus="ensureResourcesLoaded"
                    @update:show="(open) => open && ensureResourcesLoaded()"
                />
            </div>

            <div>
                <label>Admin Name:</label>
                <n-select
                    v-model:value="selectedAdminId"
                    :options="adminOptions"
                    :loading="adminLoading"
                    placeholder="All admins"
                    clearable
                    filterable
                    style="width: 100%"
                    @focus="ensureAdminsLoaded"
                    @update:show="(open) => open && ensureAdminsLoaded()"
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

        <n-modal v-model:show="showModal" preset="dialog" title="Admin Activity Report Ready">
            <template #default> Your admin activity report is ready to download. </template>
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
import { ref } from "vue";
import { request } from "@/utils/api";
import { useReportChunks } from "@/composables/useReportChunks";

const emit = defineEmits(["report-created"]);

const dateRange = ref(null);
const selectedAction = ref(null);
const selectedResource = ref(null);
const selectedAdminId = ref(null);

const actionOptions = ref([]);
const actionLoading = ref(false);
let actionsLoaded = false;

const resourceOptions = ref([]);
const resourceLoading = ref(false);
let resourcesLoaded = false;

const adminOptions = ref([]);
const adminLoading = ref(false);
let adminsLoaded = false;

function formatDate(ts) {
    return new Date(ts).toLocaleDateString("sv-SE"); // yyyy-MM-dd
}

const {
    loading,
    progress: progressPercent,
    showModal,
    downloadUrl,
    runReport,
} = useReportChunks("admin-activity", 300);

async function ensureActionsLoaded() {
    if (actionsLoaded) return;
    actionLoading.value = true;
    try {
        const res = await request({ url: "/admin-actions/action-types" });
        actionOptions.value = (res || []).map((action) => ({
            label: action,
            value: action,
        }));
        actionsLoaded = true;
    } catch (e) {
        console.error("Failed to load action types", e);
        window.$message?.error("Failed to load action types");
    } finally {
        actionLoading.value = false;
    }
}

async function ensureResourcesLoaded() {
    if (resourcesLoaded) return;
    resourceLoading.value = true;
    try {
        const res = await request({ url: "/admin-actions/resource-types" });
        resourceOptions.value = (res || []).map((resource) => ({
            label: resource,
            value: resource,
        }));
        resourcesLoaded = true;
    } catch (e) {
        console.error("Failed to load resource types", e);
        window.$message?.error("Failed to load resource types");
    } finally {
        resourceLoading.value = false;
    }
}

async function ensureAdminsLoaded() {
    if (adminsLoaded) return;
    adminLoading.value = true;
    try {
        const res = await request({ url: "/admin-actions/admins" });
        adminOptions.value = (res || []).map((admin) => ({
            label: admin.name,
            value: admin.id,
        }));
        adminsLoaded = true;
    } catch (e) {
        console.error("Failed to load admins", e);
        window.$message?.error("Failed to load admins list");
    } finally {
        adminLoading.value = false;
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

    if (selectedAction.value) {
        payload.action_type = selectedAction.value;
    }

    if (selectedResource.value) {
        payload.resource_type = selectedResource.value;
    }

    if (selectedAdminId.value) {
        payload.admin_id = selectedAdminId.value;
    }

    await runReport(payload);

    if (downloadUrl.value) {
        emit("report-created");
    }
}
</script>

