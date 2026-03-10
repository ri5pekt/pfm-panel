import { ref, computed, watch } from "vue";

// Internal "work tabs" for Replacement Orders (SPA-level tabs, not browser tabs)
const STORAGE_KEY = "pfm-panel:replacement-work-tabs:v1";

const _initialized = ref(false);
const _ids = ref([]); // string[]
const _activeKey = ref("replacements-main");

function mainKey() {
    return "replacements-main";
}

function keyForReplacementId(id) {
    return `replacement:${String(id)}`;
}

function replacementIdFromKey(key) {
    if (typeof key !== "string") return null;
    if (!key.startsWith("replacement:")) return null;
    const id = key.slice("replacement:".length).trim();
    return id ? id : null;
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        const ids = Array.isArray(parsed?.replacementIds) ? parsed.replacementIds.map((x) => String(x)) : [];
        const active = typeof parsed?.activeKey === "string" ? parsed.activeKey : mainKey();
        _ids.value = Array.from(new Set(ids)).filter(Boolean);
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
                replacementIds: _ids.value,
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
    watch(_ids, saveToStorage, { deep: true });
    watch(_activeKey, saveToStorage);
}

export function useReplacementWorkTabs() {
    ensureInit();

    const tabs = computed(() => {
        return [
            { key: mainKey(), label: "Replacement Orders", closable: false },
            ..._ids.value.map((id) => ({
                key: keyForReplacementId(id),
                label: `#${id}`,
                closable: true,
                replacementId: id,
            })),
        ];
    });

    function setActiveKey(key) {
        _activeKey.value = key || mainKey();
    }

    function openReplacement(id, { activate = true } = {}) {
        const sid = String(id);
        if (sid && !_ids.value.includes(sid)) {
            _ids.value.push(sid);
        }
        if (activate) {
            _activeKey.value = keyForReplacementId(sid);
        }
        return _activeKey.value;
    }

    function closeTab(key) {
        if (!key || key === mainKey()) return _activeKey.value;
        const id = replacementIdFromKey(key);
        if (!id) return _activeKey.value;

        _ids.value = _ids.value.filter((x) => String(x) !== String(id));

        if (_activeKey.value === key) {
            const lastId = _ids.value[_ids.value.length - 1];
            _activeKey.value = lastId ? keyForReplacementId(lastId) : mainKey();
        }
        return _activeKey.value;
    }

    function clearAll() {
        _ids.value = [];
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
        keyForReplacementId,
        replacementIdFromKey,

        openReplacement,
        closeTab,
        setActiveKey,
        clearAll,
    };
}


