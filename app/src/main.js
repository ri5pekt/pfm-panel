// main.js
import { createApp, h } from "vue";
import App from "./App.vue";

import naive from "naive-ui";
import { createRouter, createWebHashHistory } from "vue-router";
import { NMessageProvider, NConfigProvider, NDialogProvider } from "naive-ui";

import OrdersList from "./views/OrdersList.vue";
import SubscriptionsList from "./views/SubscriptionsList.vue";
import CustomersList from "./views/CustomersList.vue";
import OrderView from "./views/OrderView.vue";
import SubscriptionView from "./views/SubscriptionView.vue";
import CustomerView from "./views/CustomerView.vue";
import StatisticsView from "./views/StatisticsView.vue";
import { can } from "@/utils/permissions";

import "./assets/main.css";

const routes = [
    { path: "/", redirect: "/orders" },
    { path: "/orders", component: OrdersList, name: "orders" },
    { path: "/subscriptions", component: SubscriptionsList, name: "subscriptions" },
    { path: "/orders/:id", component: OrderView, props: true, name: "order-view" },
    { path: "/subscriptions/:id", component: SubscriptionView, props: true, name: "subscription-view" },
    { path: "/stats", component: StatisticsView, name: "stats" },
    { path: "/customers", component: CustomersList, name: "customers" },
    { path: "/customers/:id", component: CustomerView, props: true, name: "customer-view" }, // ← Add this!
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

if (typeof window.PFMPanelData === "undefined") {
    window.PFMPanelData = {
        restUrl: "https://particlestage.wpengine.com/wp-json/pfm-panel/v1/orders",
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
                h(NMessageProvider, null, {
                    default: () =>
                        h(NDialogProvider, null, {
                            default: () => h(App),
                        }),
                }),
        }),
});

app.config.globalProperties.$can = can;

app.use(naive);
app.use(router);
app.mount("#pfm-panel-app");
