// api.js
const API_URL = import.meta.env.VITE_WC_API_URL;

function joinUrl(base, path) {
    const b = String(base || "").replace(/\/+$/, "");
    const p = String(path || "").replace(/^\/+/, "");
    if (!b) return `/${p}`;
    if (!p) return `${b}/`;
    return `${b}/${p}`;
}

function getApiBase() {
    // WP admin embed always injects PFMPanelData.restUrl
    if (window?.PFMPanelData?.restUrl) return window.PFMPanelData.restUrl;
    // Localhost dev: route through the Vite proxy (/wp-json → particleformen.com) to avoid CORS
    const isDev = location.hostname === "localhost" || location.hostname === "127.0.0.1";
    if (isDev) return "/wp-json/pfm-panel/v1";
    // External panel (panel.pfm-qa.com): use the absolute URL from .env
    return API_URL;
}

export const apiBase = getApiBase();

export function isExternalServer() {
    // External = no WP nonce injected; covers both localhost dev and panel.pfm-qa.com
    return !window?.PFMPanelData?.nonce;
}

export async function request({
    url,
    method = "GET",
    body = null,
    useCustomApi = true, // (kept for compat, not used)
    headers = {},
    raw = false,
    throwOnError = true,
    params = null,
}) {
    let fullUrl = joinUrl(apiBase, url);

    if (params && method.toUpperCase() === "GET") {
        const qs = new URLSearchParams(params).toString();
        if (qs) fullUrl += (fullUrl.includes("?") ? "&" : "?") + qs;
    }

    const nonce = window?.PFMPanelData?.nonce;

    let authHeaders = {};
    if (nonce) {
        authHeaders = { "X-WP-Nonce": nonce };
    } else {
        const user = getStoredUser();
        if (user?.token) {
            authHeaders = { Authorization: `Bearer ${user.token}` };
        }
    }

    const finalHeaders = {
        "Content-Type": "application/json",
        ...authHeaders,
        ...headers,
    };

    const res = await fetch(fullUrl, {
        method,
        headers: finalHeaders,
        body: body ? JSON.stringify(body) : undefined,
        credentials: nonce ? "include" : "omit",
    });

    // Read once; reuse for both success/error
    const text = await res.text();
    let data;
    try {
        data = text ? JSON.parse(text) : undefined;
    } catch {
        // non-JSON response; keep `data` undefined and preserve `text`
    }

    if (!res.ok) {
        if (!throwOnError) {
            return new Response(text, { status: res.status, headers: res.headers });
        }

        const pretty = data?.error || data?.message || text || "Request failed";
        const err = new Error(`Request failed: ${res.status} - ${pretty}`);
        err.status = res.status;
        err.body = data; // parsed JSON if available
        err.bodyText = text; // raw text fallback
        err.headers = Object.fromEntries(res.headers.entries());
        err.url = fullUrl;
        throw err;
    }

    // If caller wants the raw Response
    if (raw) return new Response(text, { status: res.status, headers: res.headers });

    // Return parsed JSON when possible; otherwise empty object (e.g., 204)
    return typeof data !== "undefined" ? data : {};
}

const STORED_USER_KEY = "pfm_panel_user";

export function getStoredUser() {
    try {
        const raw = localStorage.getItem(STORED_USER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function storeUser(user) {
    localStorage.setItem(STORED_USER_KEY, JSON.stringify(user));
}

export function logout() {
    localStorage.removeItem(STORED_USER_KEY);
}
