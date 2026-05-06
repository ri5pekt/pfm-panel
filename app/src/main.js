// main.js
import { createApp, h } from "vue";
import App from "./App.vue";

import naive from "naive-ui";
import { createRouter, createWebHashHistory } from "vue-router";
import { NMessageProvider, NConfigProvider, NDialogProvider } from "naive-ui";

import OrdersList from "./views/orders/OrdersList.vue";
import OrderView from "./views/orders/OrderView.vue";

import SubscriptionsList from "./views/subscriptions/SubscriptionsList.vue";
import SubscriptionView from "./views/subscriptions/SubscriptionView.vue";

import CustomersList from "./views/customers/CustomersList.vue";
import CustomerView from "./views/customers/CustomerView.vue";

import StatisticsView from "./views/statistics/StatisticsView.vue";

import ReplacementOrdersList from "./views/replacements/ReplacementOrdersList.vue";
import ReplacementOrderView from "./views/replacements/ReplacementOrderView.vue";

import ReportsList from "./views/reports/ReportsList.vue";
import ReportView from "./views/reports/ReportView.vue";

import CouponsList from "./views/coupons/CouponsList.vue";

import AdminActionsList from "./views/admin/AdminActionsList.vue";

import LoginView from "./views/LoginView.vue";

import { can } from "@/utils/permissions";
import { isExternalServer, getStoredUser } from "@/utils/api";

import "./assets/main.css";

const routes = [
    { path: "/", redirect: "/orders" },
    { path: "/orders", component: OrdersList, name: "orders" },
    { path: "/subscriptions", component: SubscriptionsList, name: "subscriptions" },
    { path: "/orders/:id", component: OrderView, props: true, name: "order-view" },
    { path: "/subscriptions/:id", component: SubscriptionView, props: true, name: "subscription-view" },
    { path: "/stats", component: StatisticsView, name: "stats" },
    { path: "/customers", component: CustomersList, name: "customers" },
    { path: "/customers/:id", component: CustomerView, props: true, name: "customer-view" },
    { path: "/replacements", component: ReplacementOrdersList, name: "replacements" },
    { path: "/replacements/:id", component: ReplacementOrderView, props: true, name: "replacement-view" },
    { path: "/reports", name: "reports", component: ReportsList },
    { path: "/reports/:key", name: "report-view", component: ReportView, props: true },
    { path: "/admin-activity", component: AdminActionsList, name: "admin-activity", meta: { requiresAdmin: true } },
    { path: "/coupons", component: CouponsList, name: "coupons" },
    { path: "/login", component: LoginView, name: "login" },
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

router.beforeEach((to) => {
    if (to.name === "login") return true;
    if (!isExternalServer()) return true;
    const user = getStoredUser();
    if (!user?.token) return { name: "login" };
    if (user.exp && user.exp < Math.floor(Date.now() / 1000)) {
        localStorage.removeItem("pfm_panel_user");
        return { name: "login" };
    }
});

const _isDev = location.hostname === "localhost" || location.hostname === "127.0.0.1";
if (_isDev && typeof window.PFMPanelData === "undefined") {
    window.PFMPanelData = {
        restUrl: "https://www.particleformen.com/wp-json/pfm-panel/v1",
        nonce: "3597e27540",
        user: {
            first_name: "Local",
            last_name: "Dev",
            full_name: "Local Dev",
            roles: ["administrator"],
        },
    };
}

const app = createApp({
    render: () =>
        h(NConfigProvider, null, {
            default: () =>
                h(
                    NMessageProvider,
                    {
                        placement: "top", // or 'top'
                        containerStyle: { top: "72px" }, // add gap from top
                        // duration: 3000, closable: true, max: 3, etc.
                    },
                    {
                        default: () =>
                            h(NDialogProvider, null, {
                                default: () => h(App),
                            }),
                    }
                ),
        }),
});

app.config.globalProperties.$can = can;

app.use(naive);
app.use(router);
app.mount("#pfm-panel-app");
