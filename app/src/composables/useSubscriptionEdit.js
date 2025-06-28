// useSubscriptionEdit.js
import { ref, watch, h, computed } from "vue";
import { request } from "@/utils/api";

export function useSubscriptionEdit({ subscription, subscriptionId, message, emit }) {
    const editMode = ref(false);
    const isEditing = ref(false);
    const showEditConfirmModal = ref(false);

    const editItems = ref([]);
    const removedItems = ref([]);

    const productOptions = ref([]);
    const productLoading = ref(false);
    const productLoaded = ref(false);
    const autoTaxCalc = ref(true);
    // Setup edit state
    function getEditItem(id) {
        return editItems.value.find((i) => i.id === id) || {};
    }

    function onEditItemChange(id, field, value) {
        const item = getEditItem(id);
        if (!item) return;
        if (item[field] !== value) {
            item[field] = value;
            item.changed = true;
            if (item.isNew && field === "quantity") {
                const price = Number(item.original_price) || 0;
                const qty = Number(item.quantity) || 0;
                item.total = Math.round(qty * price * 100) / 100;
            } else if (field === "quantity") {
                const orig = (subscription.value.line_items || []).find((i) => i.id === id);
                if (orig && orig.quantity > 0) {
                    const perUnitTotal = orig.total / orig.quantity;
                    const perUnitTax = orig.total_tax / orig.quantity;
                    item.total = Math.round(perUnitTotal * value * 100) / 100;
                    item.tax = Math.round(perUnitTax * value * 100) / 100;
                }
            }
        }
    }

    async function loadProductOptions() {
        if (productLoaded.value) return;
        productLoading.value = true;
        try {
            const res = await request({ url: "/subscriptions/products" });
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
        }
    }

    function removeItem(subscriptionItem) {
        if (!editMode.value) return;
        if (subscriptionItem.isNew) {
            editItems.value = editItems.value.filter((i) => i.id !== subscriptionItem.id);
            return;
        }
        const editItem = editItems.value.find((i) => i.id === subscriptionItem.id && !i.isNew);
        if (editItem) {
            editItem.removed = true;
            if (!removedItems.value.includes(editItem.id)) {
                removedItems.value.push(editItem.id);
            }
        }
    }
    function isItemRemoved(id) {
        if (!editMode.value) return false;
        const item = editItems.value.find((i) => i.id === id);
        return !!(item && item.removed);
    }

    function enterEditMode() {
        editItems.value = (subscription.value.line_items || []).map((i) => ({
            id: i.id,
            quantity: i.quantity,
            total: +i.total,
            tax: +i.total_tax,
            name: i.name,
            sku: i.sku || "",
            unit_price: i.total / (i.quantity || 1),
            changed: false,
            isNew: false,
            removed: false,
        }));
        removedItems.value = [];
        editMode.value = true;
        showEditConfirmModal.value = false;
        isEditing.value = false;
    }
    function cancelEdit() {
        editMode.value = false;
        editItems.value = [];
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
                auto_tax: autoTaxCalc.value,
            };
            console.log("Submitting edit payload:", payload);
            const res = await request({
                url: `/subscriptions/${subscriptionId}/edit`,
                method: "POST",
                body: payload,
            });
            if (!res.success) {
                throw new Error(res.message || "Edit failed");
            }
            message && message.success("Subscription updated successfully!");
            emit && emit("update-subscription");
            cancelEdit();
        } catch (e) {
            message && message.error(`Edit failed: ${e.message}`);
            console.error(e);
        } finally {
            isEditing.value = false;
            showEditConfirmModal.value = false;
        }
    }

    // Process Renewal
    const showProcessRenewalModal = ref(false);
    const processingRenewal = ref(false);

    async function processRenewal() {
        processingRenewal.value = true;
        try {
            const res = await request({
                url: `/subscriptions/${subscriptionId}/actions`,
                method: "POST",
                body: { action: "process_renewal" },
            });
            if (res && res.success) {
                message && message.success("Renewal processed successfully!");
                emit && emit("update-subscription");
            } else {
                throw new Error(res && res.message ? res.message : "Failed to process renewal");
            }
        } catch (e) {
            message && message.error("Error processing renewal: " + (e.message || e));
        } finally {
            processingRenewal.value = false;
            showProcessRenewalModal.value = false;
        }
    }

    // Subtotal, tax, etc.
    const subtotal = computed(() =>
        editMode.value
            ? editItems.value.filter((i) => !i.removed).reduce((sum, i) => sum + Number(i.total || 0), 0)
            : (subscription.value.line_items || []).reduce((sum, i) => sum + Number(i.total || 0), 0)
    );
    const totalTaxAmount = computed(() =>
        editMode.value
            ? editItems.value.filter((i) => !i.removed).reduce((sum, i) => sum + Number(i.tax || 0), 0)
            : (subscription.value.line_items || []).reduce((sum, i) => sum + Number(i.total_tax || 0), 0)
    );

    watch(
        () => subscription.value,
        () => {
            if (editMode.value) enterEditMode();
        }
    );

    return {
        editMode,
        isEditing,
        showEditConfirmModal,
        editItems,
        enterEditMode,
        cancelEdit,
        proceedEdit,
        confirmEdit,
        getEditItem,
        onEditItemChange,
        removeItem,
        isItemRemoved,
        productOptions,
        productLoading,
        onAddProduct,
        onProductSelected,
        subtotal,
        totalTaxAmount,
        autoTaxCalc,
        showProcessRenewalModal,
        processingRenewal,
        processRenewal,
    };
}
