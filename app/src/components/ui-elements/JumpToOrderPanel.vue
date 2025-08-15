<!-- JumpToOrderPanel.vue -->
<template>
    <div class="jump-to-order">
        <div class="row">
            <n-input
                v-model:value="input"
                placeholder="Jump to Order ID"
                style="width: 170px"
                @keyup.enter="go"
            />
            <n-button size="medium" type="primary" @click="go">Go</n-button>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useMessage } from "naive-ui";

const router = useRouter();
const message = useMessage();
const input = ref("");

function go() {
    const raw = (input.value || "").trim();
    // allow "#3228770", URLs, or any text with digits
    const match = raw.match(/\d{4,}/); // basic sanity: at least 4 digits
    const id = match ? parseInt(match[0], 10) : NaN;

    if (!id || id <= 0) {
        message.warning("Enter a valid order ID");
        return;
    }
    router.push({ name: "order-view", params: { id } });
}
</script>

<style scoped>
.row {
    display: flex;
    gap: 6px;
    align-items: center;
}
</style>
