import { ref, computed, watch } from "vue";

// Internal "work tabs" for Orders (SPA-level tabs, not browser tabs)
// Persisted so staff can refresh and keep their open order tabs.
const STORAGE_KEY = "pfm-panel:order-work-tabs:v1";

const _initialized = ref(false);
const _orderIds = ref([]); // string[]
const _activeKey = ref("orders-main");

function mainKey() {
    return "orders-main";
}

function keyForOrderId(id) {
    return `order:${String(id)}`;
}

function orderIdFromKey(key) {
    if (typeof key !== "string") return null;
    if (!key.startsWith("order:")) return null;
    const id = key.slice("order:".length).trim();
    return id ? id : null;
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        const ids = Array.isArray(parsed?.orderIds) ? parsed.orderIds.map((x) => String(x)) : [];
        const active = typeof parsed?.activeKey === "string" ? parsed.activeKey : mainKey();
        _orderIds.value = Array.from(new Set(ids)).filter(Boolean);
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
                orderIds: _orderIds.value,
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

    watch(_orderIds, saveToStorage, { deep: true });
    watch(_activeKey, saveToStorage);
}

export function useOrderWorkTabs() {
    ensureInit();

    const tabs = computed(() => {
        return [
            { key: mainKey(), label: "Orders", closable: false },
            ..._orderIds.value.map((id) => ({
                key: keyForOrderId(id),
                label: `#${id}`,
                closable: true,
                orderId: id,
            })),
        ];
    });

    function setActiveKey(key) {
        _activeKey.value = key || mainKey();
    }

    function openOrder(id, { activate = true } = {}) {
        const sid = String(id);
        if (sid && !_orderIds.value.includes(sid)) {
            _orderIds.value.push(sid);
        }
        if (activate) {
            _activeKey.value = keyForOrderId(sid);
        }
        return _activeKey.value;
    }

    function closeTab(key) {
        if (!key || key === mainKey()) return _activeKey.value;
        const id = orderIdFromKey(key);
        if (!id) return _activeKey.value;

        _orderIds.value = _orderIds.value.filter((x) => String(x) !== String(id));

        // If we closed the active tab, pick the last tab or fall back to main.
        if (_activeKey.value === key) {
            const lastId = _orderIds.value[_orderIds.value.length - 1];
            _activeKey.value = lastId ? keyForOrderId(lastId) : mainKey();
        }
        return _activeKey.value;
    }

    function clearAll() {
        _orderIds.value = [];
        _activeKey.value = mainKey();
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // ignore
        }
    }

    return {
        // state
        tabs,
        activeKey: _activeKey,

        // helpers
        mainKey,
        keyForOrderId,
        orderIdFromKey,

        // actions
        openOrder,
        closeTab,
        setActiveKey,
        clearAll,
    };
}


