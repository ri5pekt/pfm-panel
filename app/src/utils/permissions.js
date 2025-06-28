// permissions.js

export const ROLE_CAPABILITIES = {
    administrator: {
        label: "Admin",
        caps: {
            edit_orders_info: true,
            edit_orders_items: false,
            refund_orders: false,
            edit_subscriptions: false,
            edit_users: false,
            assume_user: true,
        },
    },
    pfm_operator_1: {
        label: "Role 1 - View Only",
        caps: {
            edit_orders_info: false,
            edit_orders_items: false,
            refund_orders: false,
            edit_subscriptions: false,
            edit_users: false,
            assume_user: false,
        },
    },
    pfm_operator_2: {
        label: "Role 2 - Full Operator",
        caps: {
            edit_orders_info: true,
            edit_orders_items: true,
            refund_orders: true,
            edit_subscriptions: true,
            edit_users: true,
            assume_user: true,
        },
    },
    pfm_operator_3: {
        label: "Role 3 - Subs + Users",
        caps: {
            edit_orders_info: true,
            edit_orders_items: false,
            refund_orders: false,
            edit_subscriptions: true,
            edit_users: true,
            assume_user: true,
        },
    },
    pfm_operator_4: {
        label: "Role 4 - Users Only",
        caps: {
            edit_orders_info: true,
            edit_orders_items: false,
            refund_orders: false,
            edit_subscriptions: false,
            edit_users: true,
            assume_user: true,
        },
    },
};

// 🔍 Get all caps combined for a given list of roles
export function getCombinedCapabilities(roles = []) {
    return roles.reduce((combined, role) => {
        const caps = ROLE_CAPABILITIES[role]?.caps || {};
        for (const [cap, value] of Object.entries(caps)) {
            if (value) combined[cap] = true;
        }
        return combined;
    }, {});
}

// 👮‍♂️ Check if user has a specific capability (based on PFMPanelData.roles)
export function can(capability) {
    const roles = window?.PFMPanelData?.user?.roles || [];
    const caps = getCombinedCapabilities(roles);
    return !!caps[capability];
}
