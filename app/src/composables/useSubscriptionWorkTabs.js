import { ref, computed, watch } from "vue";

// Internal "work tabs" for Subscriptions (SPA-level tabs, not browser tabs)
const STORAGE_KEY = "pfm-panel:subscription-work-tabs:v1";

const _initialized = ref(false);
const _subscriptionIds = ref([]); // string[]
const _activeKey = ref("subscriptions-main");

function mainKey() {
    return "subscriptions-main";
}

function keyForSubscriptionId(id) {
    return `subscription:${String(id)}`;
}

function subscriptionIdFromKey(key) {
    if (typeof key !== "string") return null;
    if (!key.startsWith("subscription:")) return null;
    const id = key.slice("subscription:".length).trim();
    return id ? id : null;
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        const ids = Array.isArray(parsed?.subscriptionIds)
            ? parsed.subscriptionIds.map((x) => String(x))
            : [];
        const active = typeof parsed?.activeKey === "string" ? parsed.activeKey : mainKey();
        _subscriptionIds.value = Array.from(new Set(ids)).filter(Boolean);
        _activeKey.value = active;
    } catch {
        // ignore
    }
}

function saveToStorage() {
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                subscriptionIds: _subscriptionIds.value,
                activeKey: _activeKey.value,
            })
        );
    } catch {
        // ignore
    }
}

function ensureInit() {
    if (_initialized.value) return;
    _initialized.value = true;
    loadFromStorage();
    watch(_subscriptionIds, saveToStorage, { deep: true });
    watch(_activeKey, saveToStorage);
}

export function useSubscriptionWorkTabs() {
    ensureInit();

    const tabs = computed(() => {
        return [
            { key: mainKey(), label: "Subscriptions", closable: false },
            ..._subscriptionIds.value.map((id) => ({
                key: keyForSubscriptionId(id),
                label: `#${id}`,
                closable: true,
                subscriptionId: id,
            })),
        ];
    });

    function setActiveKey(key) {
        _activeKey.value = key || mainKey();
    }

    function openSubscription(id, { activate = true } = {}) {
        const sid = String(id);
        if (sid && !_subscriptionIds.value.includes(sid)) {
            _subscriptionIds.value.push(sid);
        }
        if (activate) {
            _activeKey.value = keyForSubscriptionId(sid);
        }
        return _activeKey.value;
    }

    function closeTab(key) {
        if (!key || key === mainKey()) return _activeKey.value;
        const id = subscriptionIdFromKey(key);
        if (!id) return _activeKey.value;

        _subscriptionIds.value = _subscriptionIds.value.filter((x) => String(x) !== String(id));

        if (_activeKey.value === key) {
            const lastId = _subscriptionIds.value[_subscriptionIds.value.length - 1];
            _activeKey.value = lastId ? keyForSubscriptionId(lastId) : mainKey();
        }
        return _activeKey.value;
    }

    function clearAll() {
        _subscriptionIds.value = [];
        _activeKey.value = mainKey();
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // ignore
        }
    }

    return {
        tabs,
        activeKey: _activeKey,

        mainKey,
        keyForSubscriptionId,
        subscriptionIdFromKey,

        openSubscription,
        closeTab,
        setActiveKey,
        clearAll,
    };
}


