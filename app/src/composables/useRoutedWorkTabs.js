import { computed, watch } from "vue";

/**
 * Bridge a "work tabs" store to vue-router.
 *
 * Expected store adapter shape:
 * {
 *   tabs: ComputedRef<Array<{key,label,closable}>>,
 *   activeKey: Ref<string>,
 *   mainKey: () => string,
 *   keyForId: (id) => string,
 *   idFromKey: (key) => string|null,
 *   open: (id, {activate}) => void,
 *   close: (key) => string, // returns nextActiveKey
 *   setActive: (key) => void,
 * }
 */
export function useRoutedWorkTabs({ route, router, listRouteName, viewRouteName, routeParam = "id", store }) {
    const isInSection = computed(() => route.name === listRouteName || route.name === viewRouteName);
    const showBar = computed(() => isInSection.value && store.tabs.value.length > 1);

    watch(
        () => [route.name, route.params?.[routeParam]],
        ([name, id]) => {
            if (name === listRouteName) {
                store.setActive(store.mainKey());
                return;
            }
            if (name === viewRouteName && id) {
                // Ensure tab exists for deep links / refresh
                store.open(id, { activate: false });
                store.setActive(store.keyForId(id));
            }
        },
        { immediate: true }
    );

    function onChange(key) {
        store.setActive(key);
        if (key === store.mainKey()) {
            router.push({ name: listRouteName });
            return;
        }
        const id = store.idFromKey(key);
        if (id) {
            router.push({ name: viewRouteName, params: { [routeParam]: id } });
        }
    }

    function onClose(key) {
        const before = store.activeKey.value;
        const nextActive = store.close(key);
        if (before === key && isInSection.value) {
            onChange(nextActive);
        }
    }

    return {
        showBar,
        isInSection,
        tabs: store.tabs,
        activeKey: computed(() => store.activeKey.value),
        onChange,
        onClose,
    };
}


