import { ref, onMounted, onBeforeUnmount } from "vue";

const isMobile = ref(false);

function updateIsMobile() {
    if (typeof window === "undefined") return;
    isMobile.value = window.matchMedia("(max-width: 768px)").matches;
}

export function useIsMobile() {
    onMounted(() => {
        updateIsMobile();
        window.addEventListener("resize", updateIsMobile);
    });

    onBeforeUnmount(() => {
        window.removeEventListener("resize", updateIsMobile);
    });

    return { isMobile };
}
