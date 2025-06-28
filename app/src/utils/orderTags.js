import { h } from "vue";
import { NTag } from "naive-ui";

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

    function makeTag(label, bgColor, textColor = "#fff") {
        return h(
            NTag,
            {
                size: "small",
                style: {
                    "--n-border": "none",
                    backgroundColor: bgColor,
                    color: textColor,
                    borderRadius: "10px",
                    fontSize: "11px",
                    padding: "0px 6px",
                    lineHeight: "1",
                    border: "none",
                    boxShadow: "none",
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

    return tags;
}
