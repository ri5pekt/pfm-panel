// particleformen/wp-content/plugins/pfm-panel/app/src/utils/orderTags.js
import { h } from "vue";
import { NTag } from "naive-ui";
import yotpoLogo from "@/assets/images/icons/tags/yotpo-logo.svg";

export function getMetaValue(row, key) {
    return (row.meta_data || []).find((m) => m.key === key)?.value || null;
}

export function getSpecialTags(row) {
    const tags = [];

    const ppuStatus = getMetaValue(row, "ppu_status");
    const ppuProductsCount = parseInt(getMetaValue(row, "ppu_products_count")) || 0;
    const fbOrderId = getMetaValue(row, "facebook_order_id");
    const walmartOrderId = getMetaValue(row, "walmart_order_id");
    const hasRenewal = !!getMetaValue(row, "_subscription_renewal");
    const hasParent = !!getMetaValue(row, "_subscription_parent");
    const upsellAmount = parseFloat(getMetaValue(row, "_upsell_amount")) || 0;
    const hotjarUrl = getMetaValue(row, "_hotjar_last_recording_url") || null;
    const cartSidebarTotal = parseFloat(getMetaValue(row, "_ref-cart-sidebar-total-usd")) || 0;

    function makeTag(label, bgColor, options = {}) {
        const {
            textColor = "#fff",
            size = "small",
            fontSize = "10px",
            border = "none",
            padding = "0px 6px",
            borderRadius = "10px",
            lineHeight = "1",
            fontWeight = "bold",
        } = options;

        return h(
            NTag,
            {
                size,
                style: {
                    "--n-border": "none",
                    backgroundColor: bgColor,
                    color: textColor,
                    border,
                    fontSize,
                    padding,
                    borderRadius,
                    lineHeight,
                    boxShadow: "none",
                    fontWeight,
                    whiteSpace: "nowrap",
                },
            },
            { default: () => label }
        );
    }

    if (ppuStatus === "on-hold") tags.push(makeTag("PPU on-hold", "#FFA500"));
    if (ppuProductsCount > 0) tags.push(makeTag("PPU Added", "#FF8C00"));
    if (fbOrderId) tags.push(makeTag("Facebook", "#1877F2"));
    if (walmartOrderId) tags.push(makeTag("Walmart", "#ffc220"));
    if (hasRenewal) tags.push(makeTag("Sub Renewal", "#a259ff"));
    if (hasParent) tags.push(makeTag("Sub Parent", "#7e22ce"));
    if (upsellAmount > 0) tags.push(makeTag("BAS Added", "#00b894"));
    if (row.refunded_amount && parseFloat(row.refunded_amount) > 0) {
        tags.push(makeTag("Refunded", "#d47e78"));
    }
    if (hotjarUrl) tags.push(makeTag("Hotjar", "#d23201"));
    if (cartSidebarTotal !== 0) tags.push(makeTag("CS Added", "#3b82f6"));

    const couponMap = row.coupon_codes;
    if (couponMap && typeof couponMap === "object") {
        Object.values(couponMap).forEach((code) => {
            const isRewardCoupon = typeof code === "string" && code.toLowerCase().startsWith("reward_");
            if (isRewardCoupon) {
                const displayCode = code.slice(7); // remove "reward_"
                tags.push(
                    h(
                        NTag,
                        {
                            size: "small",
                            style: {
                                "--n-border": "none",
                                backgroundColor: "#F3F6FF",
                                color: "#2563EB",
                                border: "1px solid #DDE5FF",
                                fontSize: "11px",
                                padding: "2px 4px",
                                borderRadius: "3px",
                                lineHeight: "1",
                                boxShadow: "none",
                                fontWeight: 500,
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "6px",
                            },
                        },
                        {
                            default: () =>
                                h("span", { style: { display: "inline-flex", alignItems: "center", gap: "6px" } }, [
                                    h("img", {
                                        src: yotpoLogo,
                                        alt: "Yotpo",
                                        style: { width: "14px", height: "14px" },
                                    }),
                                    h("span", { style: { color: "#2563EB" } }, displayCode),
                                ]),
                        }
                    )
                );
            } else {
                tags.push(
                    makeTag(code, "#f1f1f1", {
                        textColor: "#555",
                        size: "small",
                        fontSize: "11px",
                        border: "1px solid #ddd",
                        padding: "2px 4px",
                        borderRadius: "3px",
                        fontWeight: "normal",
                    })
                );
            }
        });
    }

    return tags;
}
