<template>
    <div class="login-wrapper">
        <n-card class="login-card">
            <div class="login-header">
                <div class="login-title">PFM Panel</div>
            </div>

            <n-alert v-if="error" type="error" style="margin-bottom: 16px">{{ error }}</n-alert>

            <n-button
                type="primary"
                block
                size="large"
                :loading="loading"
                @click="loginWithWordPress"
            >
                Login with WordPress
            </n-button>
        </n-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { storeUser } from "@/utils/api";

const WP_BASE = "https://www.particleformen.com";

const router = useRouter();
const loading = ref(false);
const error = ref("");

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get("token");

    if (token) {
        window.history.replaceState({}, "", window.location.pathname);
        try {
            const payload = JSON.parse(atob(token.split(".")[0]));
            storeUser({
                token,
                full_name: payload.name,
                roles: payload.roles,
                exp: payload.exp,
            });
            router.push({ name: "orders" });
        } catch {
            error.value = "Invalid token received. Please try again.";
        }
    }
});

function loginWithWordPress() {
    const redirectUri = window.location.origin + window.location.pathname;
    window.location.href =
        `${WP_BASE}/?pfm_auth=1&redirect_uri=${encodeURIComponent(redirectUri)}`;
}
</script>

<style scoped>
.login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
}

.login-card {
    width: 340px;
    max-width: 95vw;
}

.login-header {
    text-align: center;
    margin-bottom: 24px;
}

.login-title {
    font-size: 22px;
    font-weight: 600;
}
</style>
