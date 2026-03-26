export default defineNuxtConfig({
  compatibilityDate: '2025-12-15',
  devtools: { enabled: true },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    databaseUrl: process.env.DATABASE_URL,
    public: {
      lanIp: process.env.LAN_IP || ''
    }
  }
})
