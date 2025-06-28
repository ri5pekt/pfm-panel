// api.js
const API_URL = import.meta.env.VITE_WC_API_URL;
const API_URL_CUSTOM = import.meta.env.VITE_WC_CUSTOM_API_URL;
const CONSUMER_KEY = import.meta.env.VITE_WC_CONSUMER_KEY;
const CONSUMER_SECRET = import.meta.env.VITE_WC_CONSUMER_SECRET;

export const authHeader = "Basic " + btoa(`${CONSUMER_KEY}:${CONSUMER_SECRET}`);
export const apiBase = API_URL;
export const apiBaseCustom = API_URL_CUSTOM;

export async function request({
    url,
    method = "GET",
    body = null,
    useCustomApi = true,
    headers = {},
    raw = false,
}) {
    const fullUrl = `${useCustomApi ? apiBaseCustom : apiBase}${url}`;
    const isDev = location.hostname === "localhost" || location.hostname === "127.0.0.1";

    const finalHeaders = {
        "Content-Type": "application/json",
        ...(isDev ? { Authorization: authHeader } : { "X-WP-Nonce": window?.PFMPanelData?.nonce || "" }),
        ...headers,
    };

    const options = {
        method,
        headers: finalHeaders,
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    const res = await fetch(fullUrl, options);

    if (!res.ok) {
        const errorText = await res.text();
        throw new Error(`Request failed: ${res.status} - ${errorText}`);
    }

    return raw ? res : res.json();
}
