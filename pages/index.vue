<script setup lang="ts">
interface Controller {
  id: number
  name: string
  discription: string | null
}

interface Reading {
  id: number
  pin: string
  value: number
  controller_id: number
  created_at: string
}

const { data: controllersData, pending: controllersPending, error: controllersError } = await useFetch<{
  controllers: Controller[]
}>('/api/controllers', {
  default: () => ({ controllers: [] })
})

const { data: networkData } = await useFetch<{ lanIp: string | null; accessUrl: string | null }>(
  '/api/system/network',
  {
    default: () => ({ lanIp: null, accessUrl: null })
  }
)

const networkHint = computed(() =>
  networkData.value.accessUrl ? null : 'Чтобы отобразить LAN IP, запустите Docker с переменной LAN_IP'
)

const selectedControllerId = ref<number | null>(null)

watch(
  () => controllersData.value.controllers,
  (controllers) => {
    if (controllers.length > 0 && selectedControllerId.value === null) {
      selectedControllerId.value = controllers[0].id
    }
  },
  { immediate: true }
)

const selectedController = computed(() =>
  controllersData.value.controllers.find((controller) => controller.id === selectedControllerId.value)
)

const {
  data: readingsData,
  pending: readingsPending,
  refresh: refreshReadings
} = await useAsyncData(
  'controller-readings',
  async () => {
    if (!selectedControllerId.value) {
      return { latest: [] as Reading[], history: [] as Reading[] }
    }

    return await $fetch<{
      latest: Reading[]
      history: Reading[]
    }>(`/api/controllers/${selectedControllerId.value}/readings`)
  },
  {
    watch: [selectedControllerId],
    default: () => ({ latest: [], history: [] })
  }
)

let refreshTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  refreshTimer = setInterval(() => {
    refreshReadings()
  }, 5000)
})

onBeforeUnmount(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})

const pinLabel: Record<string, string> = {
  thermometer: 'Температура',
  pressure: 'Давление',
  humidity: 'Влажность'
}

const valueUnit: Record<string, string> = {
  thermometer: '°C',
  pressure: 'мм рт. ст.',
  humidity: '%'
}
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Дашборд контроллеров</p>
      <h2>Мониторинг в реальном времени</h2>
    </div>
    <div class="dash-head-meta">
      <p class="muted">Обновление данных каждые 5 секунд</p>
      <p v-if="networkData.accessUrl" class="server-access">
        Адрес в локальной сети:
        <strong>{{ networkData.accessUrl }}</strong>
      </p>
      <p v-else class="muted">{{ networkHint }}</p>
    </div>
  </section>

  <section class="dashboard-layout">
    <aside class="panel controllers-panel">
      <h3>Контроллеры системы</h3>

      <p v-if="controllersPending" class="muted">Загрузка контроллеров...</p>
      <p v-else-if="controllersError" class="muted">Ошибка загрузки списка контроллеров</p>
      <p v-else-if="controllersData.controllers.length === 0" class="muted">Контроллеры не найдены</p>

      <div v-else class="controllers-list">
        <button
          v-for="controller in controllersData.controllers"
          :key="controller.id"
          class="controller-item"
          :class="{ 'controller-item--active': selectedControllerId === controller.id }"
          @click="selectedControllerId = controller.id"
        >
          <span class="controller-item__name">{{ controller.name }}</span>
          <span class="controller-item__meta">ID: {{ controller.id }}</span>
          <span class="controller-item__desc">{{ controller.discription || 'Без описания' }}</span>
        </button>
      </div>
    </aside>

    <div class="right-stack">
      <section class="panel">
        <div class="panel-head">
          <div>
            <p class="eyebrow">Данные контроллера</p>
            <h3>
              {{ selectedController?.name || 'Контроллер не выбран' }}
            </h3>
          </div>
          <p v-if="readingsPending" class="muted">Обновление...</p>
        </div>

        <div v-if="!selectedController" class="muted">
          Выберите контроллер слева, чтобы увидеть показания.
        </div>

        <div v-else-if="readingsData.latest.length === 0" class="muted">
          Для выбранного контроллера пока нет данных.
        </div>

        <div v-else class="sensor-grid">
          <article v-for="item in readingsData.latest" :key="item.pin" class="sensor-card">
            <p class="sensor-card__label">{{ pinLabel[item.pin] || item.pin }}</p>
            <p class="sensor-card__value">
              {{ item.value }}
              <span class="sensor-card__unit">{{ valueUnit[item.pin] || '' }}</span>
            </p>
            <p class="sensor-card__time">
              {{ new Date(item.created_at).toLocaleString('ru-RU') }}
            </p>
          </article>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>История измерений</h3>
          <p class="muted">Графики по последним 30 записям</p>
        </div>

        <HistoryCharts
          :history="readingsData.history"
          :pin-label="pinLabel"
          :value-unit="valueUnit"
        />
      </section>
    </div>
  </section>
</template>
