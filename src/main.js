import { createApp, h } from 'vue'
import App from './App.vue'
import naive from 'naive-ui'
import { createRouter, createWebHashHistory } from 'vue-router'
import { NMessageProvider, NConfigProvider } from 'naive-ui'

import OrdersList from './views/OrdersList.vue'
import OrderView from './views/OrderView.vue'

import './assets/main.css'

const routes = [
  { path: '/', redirect: '/orders' },
  { path: '/orders', component: OrdersList },
  { path: '/orders/:id', component: OrderView, props: true }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

createApp({
  render: () =>
    h(NConfigProvider, null, {
      default: () =>
        h(NMessageProvider, null, {
          default: () => h(App)
        })
    })
})
  .use(naive)
  .use(router)
  .mount('#pfm-panel-app')
