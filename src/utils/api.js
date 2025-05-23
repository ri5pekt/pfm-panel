const API_URL = import.meta.env.VITE_WC_API_URL
const API_URL_CUSTOM = import.meta.env.VITE_WC_CUSTOM_API_URL
const CONSUMER_KEY = import.meta.env.VITE_WC_CONSUMER_KEY
const CONSUMER_SECRET = import.meta.env.VITE_WC_CONSUMER_SECRET

export const authHeader = 'Basic ' + btoa(`${CONSUMER_KEY}:${CONSUMER_SECRET}`)
export const apiBase = API_URL
export const apiBaseCustom = API_URL_CUSTOM
