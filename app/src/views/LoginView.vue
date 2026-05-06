<template>
    <div class="login-wrapper">
        <n-card class="login-card">
            <div class="login-header">
                <div class="login-title">PFM Panel</div>
            </div>

            <n-alert v-if="autoLoginError" type="error" style="margin-bottom: 16px">
                {{ autoLoginError }}
            </n-alert>

            <template v-if="!showManual">
                <n-button
                    type="primary"
                    block
                    size="large"
                    :loading="autoLogging"
                    @click="loginWithWordPress"
                >
                    Login with WordPress
                </n-button>

                <div class="login-divider">or</div>

                <n-button text block @click="showManual = true" style="color: #aaa; font-size: 13px">
                    Enter credentials manually
                </n-button>
            </template>

            <template v-else>
                <n-form @submit.prevent="handleManualLogin">
                    <n-form-item label="Username">
                        <n-input
                            v-model:value="username"
                            placeholder="WordPress username"
                            :disabled="loading"
                        />
                    </n-form-item>
                    <n-form-item label="Application Password">
                        <n-input
                            v-model:value="appPassword"
                            type="password"
                            show-password-on="click"
                            placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                            :disabled="loading"
                        />
                    </n-form-item>

                    <n-alert v-if="manualError" type="error" style="margin-bottom: 16px">{{ manualError }}</n-alert>

                    <n-button
                        type="primary"
                        :loading="loading"
                        :disabled="!username || !appPassword"
                        attr-type="submit"
                        block
                    >
                        Sign In
                    </n-button>
                </n-form>

                <n-button text block @click="showManual = false" style="margin-top: 12px; color: #aaa; font-size: 13px">
                    ← Back
                </n-button>
            </template>
        </n-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { storeUser } from "@/utils/api";

const router = useRouter();

const WP_BASE = "https://www.particleformen.com";
const API_BASE = import.meta.env.VITE_WC_API_URL;

const showManual = ref(false);
const username = ref("");
const appPassword = ref("");
const loading = ref(false);
const autoLogging = ref(false);
const manualError = ref("");
const autoLoginError = ref("");

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const user_login = params.get("user_login");
    const password = params.get("password");

    if (user_login && password) {
        // Clear params from URL without triggering navigation
        window.history.replaceState({}, "", window.location.pathname);
        autoLogging.value = true;
        await attemptLogin(user_login, password.replace(/\s+/g, ""));
        autoLogging.value = false;
    }
});

function loginWithWordPress() {
    const successUrl = window.location.origin + window.location.pathname;
    const authorizeUrl =
        `${WP_BASE}/wp-admin/authorize-application.php` +
        `?app_name=PFM+Panel` +
        `&success_url=${encodeURIComponent(successUrl)}`;
    window.location.href = authorizeUrl;
}

async function handleManualLogin() {
    if (!username.value || !appPassword.value) return;
    loading.value = true;
    manualError.value = "";
    const err = await attemptLogin(username.value, appPassword.value.replace(/\s+/g, ""));
    if (err) manualError.value = err;
    loading.value = false;
}

async function attemptLogin(user, pass) {
    try {
        const res = await fetch(`${API_BASE}/me`, {
            headers: {
                Authorization: "Basic " + btoa(`${user}:${pass}`),
                "Content-Type": "application/json",
            },
            credentials: "include",
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            return data?.message || "Invalid credentials or insufficient permissions.";
        }

        const data = await res.json();
        storeUser({
            id: data.id,
            username: user,
            appPassword: pass,
            full_name: data.full_name,
            first_name: data.first_name,
            last_name: data.last_name,
            roles: data.roles,
        });

        router.push({ name: "orders" });
        return null;
    } catch {
        return "Could not connect to the server.";
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
    width: 380px;
    max-width: 95vw;
}

.login-header {
    text-align: center;
    margin-bottom: 28px;
}

.login-title {
    font-size: 22px;
    font-weight: 600;
}

.login-divider {
    text-align: center;
    color: #ccc;
    margin: 12px 0;
    font-size: 12px;
}
</style>
