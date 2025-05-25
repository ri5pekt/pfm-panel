import { ref } from "vue";

export function formatOrderDate(rawDate, showTime = false) {
    if (!rawDate) return "—";

    // Ensure it's treated as UTC if it lacks timezone info
    const date = new Date(rawDate.match(/Z|[+-]\d{2}:\d{2}$/) ? rawDate : rawDate + "Z");

    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr = Math.floor(diffMin / 60);

    if (diffHr < 24) {
        if (diffSec < 60) return `${diffSec} second${diffSec === 1 ? "" : "s"} ago`;
        if (diffMin < 60) return `${diffMin} minute${diffMin === 1 ? "" : "s"} ago`;
        return `${diffHr} hour${diffHr === 1 ? "" : "s"} ago`;
    }

    const options = {
        month: "short",
        day: "numeric",
        ...(showTime && {
            hour: "2-digit",
            minute: "2-digit",
            hour12: false, // This makes it 24-hour format
        }),
    };

    if (date.getFullYear() !== now.getFullYear()) {
        options.year = "numeric";
    }

    return date.toLocaleDateString(undefined, options);
}

const currentCurrency = ref("USD");

export function setCurrency(currency) {
    currentCurrency.value = currency || "USD";
}


export function formatCurrency(amount) {
    if (isNaN(amount)) amount = 0;
    return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: currentCurrency.value,
        currencyDisplay: "narrowSymbol", // <- this avoids "US$"
        minimumFractionDigits: 2,
    }).format(amount);
}
