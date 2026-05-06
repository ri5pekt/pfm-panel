<template>
    <div class="login-wrapper">
        <n-card class="login-card">
            <div class="login-header">
                <div class="login-title">PFM Panel</div>
                <div class="login-subtitle">Sign in with your WordPress Application Password</div>
            </div>

            <n-form @submit.prevent="handleLogin">
                <n-form-item label="Username">
                    <n-input
                        v-model:value="username"
                        placeholder="WordPress username"
                        :disabled="loading"
                        @keydown.enter="handleLogin"
                    />
                </n-form-item>
                <n-form-item label="Application Password">
                    <n-input
                        v-model:value="appPassword"
                        type="password"
                        show-password-on="click"
                        placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                        :disabled="loading"
                        @keydown.enter="handleLogin"
                    />
                </n-form-item>

                <n-alert v-if="error" type="error" style="margin-bottom: 16px">{{ error }}</n-alert>

                <n-button
                    type="primary"
                    :loading="loading"
                    :disabled="!username || !appPassword"
                    attr-type="submit"
                    block
                    @click="handleLogin"
                >
                    Sign In
                </n-button>
            </n-form>

            <div class="login-hint">
                Generate an Application Password in WordPress → Users → Profile → Application Passwords
            </div>
        </n-card>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { storeUser } from "@/utils/api";

const router = useRouter();

const username = ref("");
const appPassword = ref("");
const loading = ref(false);
const error = ref("");

async function handleLogin() {
    if (!username.value || !appPassword.value) return;

    loading.value = true;
    error.value = "";

    const cleanPassword = appPassword.value.replace(/\s+/g, "");
    const authHeader = "Basic " + btoa(`${username.value}:${cleanPassword}`);
    const apiUrl = import.meta.env.VITE_WC_API_URL;

    try {
        const res = await fetch(`${apiUrl}/me`, {
            headers: {
                Authorization: authHeader,
                "Content-Type": "application/json",
            },
            credentials: "include",
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            error.value = data?.message || "Invalid credentials or insufficient permissions.";
            return;
        }

        const user = await res.json();

        storeUser({
            id: user.id,
            username: username.value,
            appPassword: cleanPassword,
            full_name: user.full_name,
            first_name: user.first_name,
            last_name: user.last_name,
            roles: user.roles,
        });

        router.push({ name: "orders" });
    } catch (e) {
        error.value = "Could not connect to the server. Check your network.";
    } finally {
        loading.value = false;
    }
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
    width: 420px;
    max-width: 95vw;
}

.login-header {
    text-align: center;
    margin-bottom: 24px;
}

.login-title {
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 4px;
}

.login-subtitle {
    font-size: 13px;
    color: #888;
}

.login-hint {
    margin-top: 16px;
    font-size: 11px;
    color: #aaa;
    text-align: center;
    line-height: 1.5;
}
</style>
