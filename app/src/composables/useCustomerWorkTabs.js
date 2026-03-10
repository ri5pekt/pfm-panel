import { ref, computed, watch } from "vue";

// Internal "work tabs" for Customers (SPA-level tabs, not browser tabs)
const STORAGE_KEY = "pfm-panel:customer-work-tabs:v1";

const _initialized = ref(false);
const _customerIds = ref([]); // string[]
const _activeKey = ref("customers-main");

function mainKey() {
    return "customers-main";
}

function keyForCustomerId(id) {
    return `customer:${String(id)}`;
}

function customerIdFromKey(key) {
    if (typeof key !== "string") return null;
    if (!key.startsWith("customer:")) return null;
    const id = key.slice("customer:".length).trim();
    return id ? id : null;
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        const ids = Array.isArray(parsed?.customerIds) ? parsed.customerIds.map((x) => String(x)) : [];
        const active = typeof parsed?.activeKey === "string" ? parsed.activeKey : mainKey();
        _customerIds.value = Array.from(new Set(ids)).filter(Boolean);
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
                customerIds: _customerIds.value,
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
    watch(_customerIds, saveToStorage, { deep: true });
    watch(_activeKey, saveToStorage);
}

export function useCustomerWorkTabs() {
    ensureInit();

    const tabs = computed(() => {
        return [
            { key: mainKey(), label: "Customers", closable: false },
            ..._customerIds.value.map((id) => ({
                key: keyForCustomerId(id),
                label: `#${id}`,
                closable: true,
                customerId: id,
            })),
        ];
    });

    function setActiveKey(key) {
        _activeKey.value = key || mainKey();
    }

    function openCustomer(id, { activate = true } = {}) {
        const sid = String(id);
        if (sid && !_customerIds.value.includes(sid)) {
            _customerIds.value.push(sid);
        }
        if (activate) {
            _activeKey.value = keyForCustomerId(sid);
        }
        return _activeKey.value;
    }

    function closeTab(key) {
        if (!key || key === mainKey()) return _activeKey.value;
        const id = customerIdFromKey(key);
        if (!id) return _activeKey.value;

        _customerIds.value = _customerIds.value.filter((x) => String(x) !== String(id));

        if (_activeKey.value === key) {
            const lastId = _customerIds.value[_customerIds.value.length - 1];
            _activeKey.value = lastId ? keyForCustomerId(lastId) : mainKey();
        }
        return _activeKey.value;
    }

    function clearAll() {
        _customerIds.value = [];
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
        keyForCustomerId,
        customerIdFromKey,

        openCustomer,
        closeTab,
        setActiveKey,
        clearAll,
    };
}


