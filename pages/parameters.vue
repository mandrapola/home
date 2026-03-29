<script setup lang="ts">
interface Controller {
  id: number
  name: string
}

interface ScenarioParameter {
  key: string
  label: string
  value: number
  unit: string | null
}

interface ParametersResponse {
  controller: Controller
  parameters: ScenarioParameter[]
  updated_at: string
}

const AUTO_REFRESH_MS = 5000
let autoRefreshTimer: ReturnType<typeof setInterval> | null = null

const { data: controllersData } = await useFetch<{ controllers: Controller[] }>('/api/controllers', {
  default: () => ({ controllers: [] })
})

const selectedControllerId = ref<number | null>(null)
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const updatedAt = ref<string | null>(null)
const parameters = ref<ScenarioParameter[]>([])

watch(
  () => controllersData.value.controllers,
  (controllers) => {
    if (!selectedControllerId.value && controllers.length > 0) {
      selectedControllerId.value = controllers[0].id
    }
  },
  { immediate: true }
)

const loadParameters = async () => {
  if (loading.value) {
    return
  }

  if (!selectedControllerId.value) {
    parameters.value = []
    return
  }

  loading.value = true
  errorMessage.value = null

  try {
    const response = await $fetch<ParametersResponse>(`/api/controllers/${selectedControllerId.value}/parameters`)
    parameters.value = response.parameters
    updatedAt.value = response.updated_at
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Не удалось загрузить параметры'
  } finally {
    loading.value = false
  }
}

watch(
  selectedControllerId,
  () => {
    loadParameters()
  },
  { immediate: true }
)

onMounted(() => {
  autoRefreshTimer = setInterval(() => {
    if (!selectedControllerId.value) {
      return
    }
    loadParameters()
  }, AUTO_REFRESH_MS)
})

onBeforeUnmount(() => {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer)
    autoRefreshTimer = null
  }
})

const formatParameterValue = (parameter: ScenarioParameter) => {
  if (parameter.key.includes(':current_time') || parameter.key.endsWith(':current_time') || parameter.key === 'current_time') {
    const seconds = Math.max(0, Math.trunc(Number(parameter.value) || 0)) % 86400
    const hours = String(Math.floor(seconds / 3600)).padStart(2, '0')
    const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')
    const secs = String(seconds % 60).padStart(2, '0')
    return `${hours}:${minutes}:${secs}`
  }

  if (parameter.key.includes(':pin_state:') || parameter.key.startsWith('pin_state:')) {
    return Number(parameter.value) > 0 ? 'Вкл' : 'Выкл'
  }

  if (parameter.unit) {
    const numeric = Number(parameter.value)
    if (!Number.isFinite(numeric)) {
      return String(parameter.value)
    }
    return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(1)
  }

  return String(Math.round(Number(parameter.value)))
}

const formatUpdatedAt = computed(() => {
  if (!updatedAt.value) {
    return 'нет данных'
  }
  return new Date(updatedAt.value).toLocaleString('ru-RU')
})
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Автоматизация</p>
      <h2>Параметры сценариев</h2>
    </div>
    <div class="dash-head-meta">
      <p class="muted">Обновлено: {{ formatUpdatedAt }}</p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <label class="field">
        <span class="field__label">Контроллер</span>
        <select v-model="selectedControllerId" class="field__input">
          <option v-for="controller in controllersData.controllers" :key="controller.id" :value="controller.id">
            {{ controller.name }} (ID {{ controller.id }})
          </option>
        </select>
      </label>
    </div>

    <p v-if="errorMessage" class="settings-error">{{ errorMessage }}</p>
    <p v-if="!loading && parameters.length === 0" class="muted">Параметры пока недоступны</p>

    <div v-if="parameters.length > 0" class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Параметр</th>
            <th>Ключ</th>
            <th>Значение</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="parameter in parameters" :key="parameter.key">
            <td>{{ parameter.label }}</td>
            <td><code>{{ parameter.key }}</code></td>
            <td>
              <strong>{{ formatParameterValue(parameter) }}</strong>
              <span v-if="parameter.unit" class="muted"> {{ parameter.unit }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
