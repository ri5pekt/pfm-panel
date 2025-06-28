//useEdit.js
import { ref, watch, h } from "vue";
import { request } from "@/utils/api";

/**
 * Composable for editing order items, including:
 * - Add/remove/edit line items, fees, shipping
 * - Tracks removed items (for backend payload)
 * - Handles edit mode, confirmation, and payload shaping
 */
export function useEdit({ order, orderId, message, emit }) {
    const editMode = ref(false);
    const isEditing = ref(false);
    const showEditConfirmModal = ref(false);

    const editItems = ref([]);
    const editFees = ref([]);
    const editShipping = ref([]);

    // Track removed items (by id)
    const removedItems = ref([]);

    // For add product feature
    const productOptions = ref([]);
    const productLoading = ref(false);
    const productLoaded = ref(false);
    const autoTaxCalc = ref(true);

    // Helpers to get by id
    function getEditItem(id) {
        return editItems.value.find((i) => i.id === id) || {};
    }
    function getEditFee(id) {
        return editFees.value.find((f) => f.id === id) || {};
    }
    function getEditShipping(id) {
        return editShipping.value.find((s) => s.id === id) || {};
    }

    function onEditItemChange(id, field, value) {
        const item = getEditItem(id);
        if (!item) return;
        if (item[field] !== value) {
            item[field] = value;
            item.changed = true;
            console.log(`[onEditItemChange] Item ${id}, field ${field}, value ${value}, isNew=${item.isNew}`);

            if (item.isNew) {
                if (field === "quantity") {
                    console.log(
                        `[onEditItemChange] Before calc: original_price=${item.original_price}, quantity=${item.quantity}`
                    );
                    const price = Number(item.original_price) || 0;
                    const qty = Number(item.quantity) || 0;
                    item.total = Math.round(qty * price * 100) / 100;
                    console.log(`[onEditItemChange] After calc: total=${item.total}`);
                }
            } else if (field === "quantity") {
                const orig = (order.value.line_items || []).find((i) => i.id === id);
                if (orig && orig.quantity > 0) {
                    const perUnitTotal = orig.total / orig.quantity;
                    const perUnitTax = orig.total_tax / orig.quantity;
                    item.total = Math.round(perUnitTotal * value * 100) / 100;
                    item.tax = Math.round(perUnitTax * value * 100) / 100;
                } else {
                    item.total = 0;
                    item.tax = 0;
                }
                item.changed = true;
            }
        }
    }

    function onEditFeeChange(id, field, value) {
        const fee = getEditFee(id);
        if (fee && fee[field] !== value) {
            fee[field] = value;
            fee.changed = true;
        }
    }
    function onEditShippingChange(id, field, value) {
        const ship = getEditShipping(id);
        if (ship && ship[field] !== value) {
            ship[field] = value;
            ship.changed = true;
        }
    }

    // -------- Add Product Logic --------
    async function loadProductOptions() {
        if (productLoaded.value) return;
        productLoading.value = true;
        try {
            const res = await request({ url: "/products-by-category" });
            // Map to n-select group options
            productOptions.value = (res || []).map((group) => ({
                type: "group",
                label: group.label,
                key: group.key,
                children: (group.products || []).map((prod) => ({
                    label: prod.name,
                    value: prod.id,
                    price: prod.price,
                    sku: prod.sku,
                    image: prod.image,
                    renderLabel: () =>
                        h("div", { style: "display: flex; align-items: center;" }, [
                            prod.image
                                ? h("img", {
                                      src: prod.image,
                                      style: "width: 20px; height: 20px; margin-right: 8px;",
                                  })
                                : null,
                            h("span", prod.name),
                        ]),
                })),
            }));
            productLoaded.value = true;
        } catch (e) {
            message && message.error("Failed to load products");
        } finally {
            productLoading.value = false;
        }
    }

    function onAddProduct() {
        // Only load if not loaded before
        if (!productLoaded.value) {
            loadProductOptions().then(() => pushNewProductRow());
        } else {
            pushNewProductRow();
        }
    }

    function pushNewProductRow() {
        const newId = `new-${Date.now()}-${Math.floor(Math.random() * 10000)}`;
        editItems.value.push({
            id: newId,
            product_id: null,
            name: "",
            sku: "",
            unit_price: 0,
            quantity: 1,
            total: 0,
            tax: 0,
            isNew: true,
            changed: true,
        });
    }

    function onProductSelected(editItemId, productId) {
        // Find product in options
        let prod = null;
        for (const group of productOptions.value) {
            prod = (group.children || []).find((p) => p.value === productId);
            if (prod) break;
        }
        const item = getEditItem(editItemId);
        if (item && prod) {
            item.product_id = productId;
            item.name = prod.label;
            item.sku = prod.sku;
            item.original_price = prod.price;
            item.quantity = 1;
            item.total = prod.price;
            item.tax = 0;
            item.changed = true;
            console.log(
                `[onProductSelected] id=${editItemId}, product_id=${productId}, price=${prod.price}, item=`,
                JSON.parse(JSON.stringify(item))
            );
        } else {
            console.warn(`[onProductSelected] No product found for product_id=${productId}`);
        }
    }

    // -------- Remove Product Logic --------
    function removeItem(orderItem) {
        if (!editMode.value) return;
        // New items: remove from editItems
        if (orderItem.isNew) {
            editItems.value = editItems.value.filter((i) => i.id !== orderItem.id);
            return;
        }
        // Old items: set removed flag and track in removedItems
        const editItem = editItems.value.find((i) => i.id === orderItem.id && !i.isNew);
        if (editItem) {
            editItem.removed = true;
            if (!removedItems.value.includes(editItem.id)) {
                removedItems.value.push(editItem.id);
            }
        }
    }

    // Used in template to hide removed items in editMode
    function isItemRemoved(id) {
        if (!editMode.value) return false;
        const item = editItems.value.find((i) => i.id === id);
        return !!(item && item.removed);
    }

    // Add Fee
    function onAddFee() {
        const newId = `new-fee-${Date.now()}-${Math.floor(Math.random() * 10000)}`;
        editFees.value.push({
            id: newId,
            name: "",
            total: 0,
            tax: 0,
            isNew: true,
            changed: true,
        });
    }

    // Remove Fee
    function removeFee(fee) {
        if (!editMode.value) return;
        if (fee.isNew) {
            editFees.value = editFees.value.filter((f) => f.id !== fee.id);
            return;
        }
        // Old fees: mark as removed, track for backend
        const editFee = editFees.value.find((f) => f.id === fee.id && !f.isNew);
        if (editFee) {
            editFee.removed = true;
            editFee.changed = true;
        }
    }

    function isFeeRemoved(id) {
        if (!editMode.value) return false;
        const fee = editFees.value.find((f) => f.id === id);
        return !!(fee && fee.removed);
    }

    // -------- Core Edit Logic --------
    function enterEditMode() {
        editItems.value = (order.value.line_items || []).map((i) => ({
            id: i.id,
            quantity: i.quantity,
            total: +i.total,
            tax: +i.total_tax,
            name: i.name,
            sku: i.sku || "",
            unit_price: i.total / (i.quantity || 1),
            changed: false,
            isNew: false,
            removed: false, // for hiding in edit mode
        }));
        editFees.value = (order.value.fee_lines || []).map((f) => ({
            id: f.id,
            name: f.name,
            total: +f.total,
            tax: +f.total_tax,
            changed: false,
        }));
        editShipping.value = (order.value.shipping_lines || []).map((s) => ({
            id: s.id,
            method_title: s.method_title,
            total: +s.total,
            tax: +s.total_tax,
            changed: false,
        }));
        removedItems.value = [];
        editMode.value = true;
        showEditConfirmModal.value = false;
        isEditing.value = false;
    }

    function cancelEdit() {
        editMode.value = false;
        editItems.value = [];
        editFees.value = [];
        editShipping.value = [];
        removedItems.value = [];
        showEditConfirmModal.value = false;
        isEditing.value = false;
    }

    function proceedEdit() {
        showEditConfirmModal.value = true;
    }

    async function confirmEdit() {
        isEditing.value = true;
        try {
            const payload = {
                items: editItems.value
                    .filter((i) => i.changed && !i.isNew && !i.removed)
                    .map(({ changed, isNew, removed, ...rest }) => rest),
                new_items: editItems.value
                    .filter((i) => i.isNew && i.product_id && !i.removed)
                    .map(({ changed, isNew, removed, ...rest }) => rest),
                removed_items: removedItems.value,
                fees: editFees.value
                    .filter((f) => f.changed && !f.isNew && !f.removed)
                    .map(({ changed, isNew, removed, ...rest }) => rest),
                new_fees: editFees.value
                    .filter((f) => f.isNew && !f.removed)
                    .map(({ changed, isNew, removed, ...rest }) => rest),
                removed_fees: editFees.value.filter((f) => f.removed && !f.isNew).map((f) => f.id),
                shipping: editShipping.value.filter((s) => s.changed).map(({ changed, ...rest }) => rest),
                auto_tax: autoTaxCalc.value,
            };

            console.log("[confirmEdit] Payload:", JSON.stringify(payload, null, 2));

            const res = await request({
                url: `/orders/${orderId}/edit`,
                method: "POST",
                body: payload,
            });

            if (!res.success) {
                throw new Error(res.message || "Edit failed");
            }

            message && message.success("Order updated successfully!");
            emit && emit("updateOrder");
            cancelEdit();
        } catch (e) {
            message && message.error(`Edit failed: ${e.message}`);
            console.error(e);
        } finally {
            isEditing.value = false;
            showEditConfirmModal.value = false;
        }
    }

    watch(
        () => order.value,
        () => {
            if (editMode.value) enterEditMode();
        }
    );

    return {
        editMode,
        isEditing,
        showEditConfirmModal,
        editItems,
        editFees,
        editShipping,
        removedItems,
        enterEditMode,
        cancelEdit,
        proceedEdit,
        confirmEdit,
        getEditItem,
        getEditFee,
        getEditShipping,
        onEditItemChange,
        onEditFeeChange,
        onEditShippingChange,
        removeItem,
        isItemRemoved,
        // Add product
        productOptions,
        productLoading,
        onAddProduct,
        onProductSelected,
        loadProductOptions,
        // Add fee
        onAddFee,
        removeFee,
        isFeeRemoved,
        autoTaxCalc
    };
}
