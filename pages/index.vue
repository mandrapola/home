<script setup lang="ts">
interface Controller {
  id: number
  name: string
  discription: string | null
  send_interval_seconds: number
}

interface PinConfig {
  pin: string
  label: string
  unit: string | null
  multiplier: number
  offset: number
  precision: number
  average_interval_minutes: number
  value_labels: Record<string, string>
  digital_style: string
  invert_digital_logic: boolean
  desired_digital_value: number | null
  desired_digital_updated_at: string | null
  power_on_duration_seconds: number | null
  show_on_dashboard: boolean
  show_on_chart: boolean
  chart_range_hours: number
  sort_order: number
}

interface EditablePinConfig extends PinConfig {
  value_labels_text: string
  digital_off_text: string
  digital_on_text: string
}

interface Reading {
  id: number
  pin: string
  value: number
  raw_value: number
  display_value: number
  display_text: string
  label: string
  unit: string | null
  digital_style: string
  invert_digital_logic: boolean
  desired_digital_value: number | null
  desired_digital_updated_at: string | null
  power_on_duration_seconds: number | null
  show_on_chart: boolean
  chart_range_hours: number
  average_interval_minutes: number
  controller_id: number
  created_at: string
}

interface TrendState {
  direction: 'up' | 'down' | 'flat'
  symbol: string
}

interface DigitalBadgePreset {
  label: string
  onIcon: string
  offIcon: string
}

const {
  data: controllersData,
  pending: controllersPending,
  error: controllersError,
  refresh: refreshControllers
} = await useFetch<{
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
const settingsModal = ref<'controller' | 'pin' | null>(null)
const settingsPinKey = ref<string | null>(null)

watch(
  () => controllersData.value.controllers,
  (controllers) => {
    if (controllers.length > 0 && selectedControllerId.value === null) {
      selectedControllerId.value = controllers[0].id
    }
  },
  { immediate: true }
)

watch(selectedControllerId, () => {
  settingsModal.value = null
  settingsPinKey.value = null
})

const selectedController = computed(() =>
  controllersData.value.controllers.find((controller) => controller.id === selectedControllerId.value)
)

const refreshIntervalLabel = computed(() => {
  const seconds = selectedController.value?.send_interval_seconds ?? 5
  return `Обновление данных каждые ${seconds} сек`
})

const {
  data: settingsData,
  pending: settingsPending,
  refresh: refreshSettings
} = await useAsyncData(
  'controller-settings',
  async () => {
    if (!selectedControllerId.value) {
      return { controller: null as Controller | null, pinConfigs: [] as PinConfig[] }
    }

    return await $fetch<{
      controller: Controller
      pinConfigs: PinConfig[]
    }>(`/api/controllers/${selectedControllerId.value}/settings`)
  },
  {
    watch: [selectedControllerId],
    default: () => ({ controller: null, pinConfigs: [] })
  }
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
let clockTimer: ReturnType<typeof setInterval> | null = null
const nowMs = ref(Date.now())

onMounted(() => {
  refreshTimer = setInterval(() => {
    refreshReadings()
  }, 5000)

  clockTimer = setInterval(() => {
    nowMs.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }

  if (clockTimer) {
    clearInterval(clockTimer)
  }
})

const editablePinConfigs = ref<EditablePinConfig[]>([])
const editableControllerName = ref('')
const editableControllerDescription = ref('')
const editableSendIntervalSeconds = ref(30)
const settingsError = ref<string | null>(null)
const settingsSavePending = ref(false)
const digitalTogglePendingPin = ref<string | null>(null)
const historyClearPendingPin = ref<string | null>(null)

const stringifyValueLabels = (valueLabels: Record<string, string>) => {
  return JSON.stringify(valueLabels, null, 2)
}

const isAnalogPin = (pin: string) => {
  const normalized = pin.trim().toLowerCase()
  return /^a\d+$/.test(normalized) || normalized === 'air_temperature' || normalized === 'air_humidity'
}
const isDigitalPin = (pin: string) => /^D\d+$/i.test(pin)
const digitalBadgePresets: Record<string, DigitalBadgePreset> = {
  power: { label: 'Питание', onIcon: '◉', offIcon: '○' },
  access: { label: 'Доступ', onIcon: '🔓', offIcon: '🔒' },
  security: { label: 'Охрана', onIcon: '⚠', offIcon: '✓' },
  signal: { label: 'Сигнал', onIcon: '⏽', offIcon: '⭘' }
}

const parseValueLabels = (input: string) => {
  if (!input.trim()) {
    return {}
  }

  const parsed = JSON.parse(input)

  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
    throw new Error('value_labels must be a JSON object')
  }

  return Object.fromEntries(
    Object.entries(parsed).map(([key, value]) => [String(key), String(value)])
  )
}

watch(
  () => settingsData.value.pinConfigs,
  (pinConfigs) => {
    editablePinConfigs.value = pinConfigs.map((config) => ({
      ...config,
      value_labels: { ...config.value_labels },
      value_labels_text: stringifyValueLabels(config.value_labels),
      digital_off_text: config.value_labels['0'] || 'Выключен',
      digital_on_text: config.value_labels['1'] || 'Включен'
    }))
  },
  { immediate: true }
)

watch(
  () => settingsData.value.controller,
  (controller) => {
    editableControllerName.value = controller?.name ?? ''
    editableControllerDescription.value = controller?.discription ?? ''
  },
  { immediate: true }
)

watch(
  () => settingsData.value.controller?.send_interval_seconds,
  (sendIntervalSeconds) => {
    editableSendIntervalSeconds.value = Number(sendIntervalSeconds ?? 30)
  },
  { immediate: true }
)

const restoreEditableSettings = () => {
  editablePinConfigs.value = settingsData.value.pinConfigs.map((config) => ({
    ...config,
    value_labels: { ...config.value_labels },
    value_labels_text: stringifyValueLabels(config.value_labels),
    digital_off_text: config.value_labels['0'] || 'Выключен',
    digital_on_text: config.value_labels['1'] || 'Включен'
  }))
  editableControllerName.value = settingsData.value.controller?.name ?? ''
  editableControllerDescription.value = settingsData.value.controller?.discription ?? ''
  editableSendIntervalSeconds.value = Number(settingsData.value.controller?.send_interval_seconds ?? 30)
  settingsError.value = null
}

const selectController = (controllerId: number) => {
  selectedControllerId.value = controllerId
}

const openControllerSettings = (controllerId: number) => {
  selectedControllerId.value = controllerId
  settingsPinKey.value = null
  settingsModal.value = 'controller'
}

const openPinSettings = (pin: string) => {
  settingsPinKey.value = pin
  settingsModal.value = 'pin'
}

const closeSettingsModal = () => {
  restoreEditableSettings()
  settingsModal.value = null
  settingsPinKey.value = null
}

const activePinConfig = computed(() =>
  settingsPinKey.value
    ? editablePinConfigs.value.find((config) => config.pin === settingsPinKey.value) ?? null
    : null
)

const analogTrendMap = computed<Record<string, TrendState>>(() => {
  const historyByPin = new Map<string, Reading[]>()

  for (const item of readingsData.value.history) {
    if (!isAnalogPin(item.pin)) {
      continue
    }

    if (!historyByPin.has(item.pin)) {
      historyByPin.set(item.pin, [])
    }

    historyByPin.get(item.pin)?.push(item)
  }

  return Object.fromEntries(
    [...historyByPin.entries()].map(([pin, rows]) => {
      const sorted = [...rows].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      )

      if (sorted.length < 2) {
        return [pin, { direction: 'flat', symbol: '→' }]
      }

      if (sorted[0].display_value > sorted[1].display_value) {
        return [pin, { direction: 'up', symbol: '↑' }]
      }

      if (sorted[0].display_value < sorted[1].display_value) {
        return [pin, { direction: 'down', symbol: '↓' }]
      }

      return [pin, { direction: 'flat', symbol: '→' }]
    })
  )
})

const dashboardItems = computed<Reading[]>(() => {
  const items: Reading[] = [...readingsData.value.latest]

  return items.sort((a, b) => {
    const aConfig = editablePinConfigs.value.find((config) => config.pin === a.pin)
    const bConfig = editablePinConfigs.value.find((config) => config.pin === b.pin)
    const aSort = aConfig?.sort_order ?? Number.MAX_SAFE_INTEGER
    const bSort = bConfig?.sort_order ?? Number.MAX_SAFE_INTEGER
    return (aSort - bSort) || a.pin.localeCompare(b.pin, undefined, { numeric: true, sensitivity: 'base' })
  })
})

const digitalStateClass = (item: Reading) => {
  const style = item.digital_style || 'power'
  const state = digitalStateValue(item) > 0 ? 'on' : 'off'
  return `sensor-card--digital-${style}-${state}`
}

const digitalBadgeIcon = (item: Reading) => {
  const preset = digitalBadgePresets[item.digital_style || 'power'] || digitalBadgePresets.power
  return digitalStateValue(item) > 0 ? preset.onIcon : preset.offIcon
}

const isInvertedPowerPin = (item: Reading) => isDigitalPin(item.pin) && item.digital_style === 'power' && item.invert_digital_logic

const digitalStateValue = (item: Reading) => {
  if (item.digital_style === 'power' && item.desired_digital_value !== null) {
    return item.desired_digital_value
  }

  const measuredValue = Number(item.display_value) > 0 ? 1 : 0
  if (isInvertedPowerPin(item)) {
    return measuredValue > 0 ? 0 : 1
  }

  return measuredValue
}

const digitalStateText = (item: Reading) => {
  const config = editablePinConfigs.value.find((entry) => entry.pin === item.pin)
  if (!config) {
    return item.display_text
  }

  return digitalStateValue(item) > 0
    ? config.digital_on_text || config.value_labels['1'] || 'Включен'
    : config.digital_off_text || config.value_labels['0'] || 'Выключен'
}

const canTogglePowerPin = (item: Reading) => isDigitalPin(item.pin) && item.digital_style === 'power'

const formatDuration = (totalSeconds: number) => {
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  const parts = [
    hours > 0 ? String(hours).padStart(2, '0') : null,
    String(minutes).padStart(2, '0'),
    String(seconds).padStart(2, '0')
  ].filter((part): part is string => part !== null)

  return parts.join(':')
}

const powerTimeState = (item: Reading): 'countdown' | 'elapsed' | null => {
  if (item.digital_style !== 'power' || digitalStateValue(item) === 0 || !item.desired_digital_updated_at) {
    return null
  }

  return item.power_on_duration_seconds ? 'countdown' : 'elapsed'
}

const powerTimeText = (item: Reading) => {
  const state = powerTimeState(item)
  if (!state || !item.desired_digital_updated_at) {
    return null
  }

  const updatedAtMs = new Date(item.desired_digital_updated_at).getTime()
  if (!Number.isFinite(updatedAtMs)) {
    return null
  }

  const elapsedSeconds = Math.max(0, Math.floor((nowMs.value - updatedAtMs) / 1000))

  if (state === 'countdown') {
    const remainingSeconds = Math.max(0, (item.power_on_duration_seconds ?? 0) - elapsedSeconds)
    return `Осталось ${formatDuration(remainingSeconds)}`
  }

  return `Работает ${formatDuration(elapsedSeconds)}`
}

const setDesiredDigitalState = async (item: Reading, nextValue: boolean) => {
  if (!selectedControllerId.value || digitalTogglePendingPin.value === item.pin) {
    return
  }

  digitalTogglePendingPin.value = item.pin

  const previousValue = item.desired_digital_value
  item.desired_digital_value = nextValue ? 1 : 0

  try {
    await $fetch(`/api/controllers/${selectedControllerId.value}/pins/${encodeURIComponent(item.pin)}/state`, {
      method: 'PUT',
      body: {
        value: nextValue ? 1 : 0
      }
    })

    await refreshReadings()
  } catch (error) {
    item.desired_digital_value = previousValue
    settingsError.value =
      error instanceof Error ? error.message : 'Не удалось сохранить состояние цифрового пина'
  } finally {
    digitalTogglePendingPin.value = null
  }
}

const clearAnalogPinHistory = async (pin: string) => {
  if (!selectedControllerId.value || historyClearPendingPin.value === pin) {
    return
  }

  historyClearPendingPin.value = pin
  settingsError.value = null

  try {
    await $fetch(`/api/controllers/${selectedControllerId.value}/pins/${encodeURIComponent(pin)}/history`, {
      method: 'DELETE'
    })

    await refreshReadings()
  } catch (error) {
    settingsError.value =
      error instanceof Error ? error.message : 'Не удалось очистить историю пина'
  } finally {
    historyClearPendingPin.value = null
  }
}

const saveSettings = async () => {
  if (!selectedControllerId.value) {
    return
  }

  settingsError.value = null
  settingsSavePending.value = true

  try {
    const payload = editablePinConfigs.value.map((config) => ({
      pin: config.pin.trim(),
      label: config.label.trim(),
      unit: config.unit?.trim() || null,
      precision: Number(config.precision),
      multiplier: Number(config.multiplier),
      offset: Number(config.offset),
      sort_order: Number(config.sort_order),
      digital_style: config.digital_style,
      invert_digital_logic: config.invert_digital_logic,
      desired_digital_value: config.desired_digital_value,
      power_on_duration_seconds: config.power_on_duration_seconds,
      show_on_dashboard: config.show_on_dashboard,
      show_on_chart: config.show_on_chart,
      chart_range_hours: Math.max(1, Math.trunc(Number(config.chart_range_hours) || 1)),
      average_interval_minutes: Math.max(1, Math.trunc(Number(config.average_interval_minutes) || 5)),
      value_labels: isDigitalPin(config.pin)
        ? {
            '0': config.digital_off_text.trim() || 'Выключен',
            '1': config.digital_on_text.trim() || 'Включен'
          }
        : parseValueLabels(config.value_labels_text)
    }))

    await $fetch(`/api/controllers/${selectedControllerId.value}/settings`, {
      method: 'PUT',
      body: {
        name: editableControllerName.value.trim(),
        discription: editableControllerDescription.value.trim() || null,
        send_interval_seconds: Math.max(1, Math.trunc(Number(editableSendIntervalSeconds.value) || 30)),
        pinConfigs: payload
      }
    })

    await Promise.all([refreshControllers(), refreshSettings(), refreshReadings()])
    closeSettingsModal()
  } catch (error) {
    settingsError.value =
      error instanceof Error ? error.message : 'Не удалось сохранить настройки контроллера'
  } finally {
    settingsSavePending.value = false
  }
}

</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Дашборд контроллеров</p>
      <h2>Мониторинг в реальном времени</h2>
    </div>
    <div class="dash-head-meta">
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
        <article
          v-for="controller in controllersData.controllers"
          :key="controller.id"
          class="controller-item"
          :class="{ 'controller-item--active': selectedControllerId === controller.id }"
        >
          <button class="controller-item__select" @click="selectController(controller.id)">
            <span class="controller-item__name">{{ controller.name }}</span>
            <span class="controller-item__meta">ID: {{ controller.id }}</span>
            <span class="controller-item__meta">
              Обновление каждые {{ controller.send_interval_seconds }} сек
            </span>
            <span class="controller-item__desc">{{ controller.discription || 'Без описания' }}</span>
          </button>
          <div class="controller-item__actions">
            <button
              type="button"
              class="controller-item__settings"
              @click="openControllerSettings(controller.id)"
            >
              Настроить
            </button>
          </div>
        </article>
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

        <div v-else-if="dashboardItems.length === 0" class="muted">
          Для выбранного контроллера пока нет данных.
        </div>

        <div v-else class="sensor-grid">
          <article
            v-for="item in dashboardItems"
            :key="item.pin"
            class="sensor-card"
            :class="isDigitalPin(item.pin) ? digitalStateClass(item) : ''"
          >
            <div class="sensor-card__head">
              <p class="sensor-card__label">{{ item.label }}</p>
              <button
                type="button"
                class="sensor-card__settings-icon"
                :title="`Настроить пин ${item.pin}`"
                @click="openPinSettings(item.pin)"
              >
                ⚙
              </button>
            </div>
            <p class="sensor-card__value" :class="{ 'sensor-card__value--state': isDigitalPin(item.pin) }">
              <template v-if="isDigitalPin(item.pin)">
                <span
                  class="sensor-card__badge"
                  :class="digitalStateClass(item)"
                >
                  <span class="sensor-card__badge-icon">{{ digitalBadgeIcon(item) }}</span>
                  {{ digitalStateText(item) }}
                </span>
              </template>
              <template v-else>
                <span>{{ item.display_value }}</span>
                <span class="sensor-card__unit">{{ item.unit || '' }}</span>
                <span
                  class="sensor-card__trend"
                  :class="`sensor-card__trend--${analogTrendMap[item.pin]?.direction || 'flat'}`"
                >
                  {{ analogTrendMap[item.pin]?.symbol || '→' }}
                </span>
              </template>
            </p>
            <div v-if="canTogglePowerPin(item)" class="sensor-card__control">
              <p
                v-if="powerTimeText(item)"
                class="sensor-card__power-time"
                :class="`sensor-card__power-time--${powerTimeState(item)}`"
              >
                {{ powerTimeText(item) }}
              </p>
              <button
                type="button"
                class="switch sensor-card__switch"
                :class="{ 'switch--on': digitalStateValue(item) > 0 }"
                :disabled="digitalTogglePendingPin === item.pin"
                @click="setDesiredDigitalState(item, digitalStateValue(item) === 0)"
              >
                {{ digitalTogglePendingPin === item.pin ? 'Сохранение...' : digitalStateValue(item) > 0 ? 'Включено' : 'Выключено' }}
              </button>
            </div>
          </article>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>История измерений</h3>
        </div>

        <HistoryCharts
          :history="readingsData.history"
        />
      </section>
    </div>
  </section>

  <div v-if="settingsModal" class="settings-modal-overlay" @click.self="closeSettingsModal">
    <section class="settings-modal panel">
      <div class="panel-head">
        <div>
          <p class="eyebrow">Настройки</p>
          <h3 v-if="settingsModal === 'controller'">
            Контроллер: {{ selectedController?.name || 'Не выбран' }}
          </h3>
          <h3 v-else>
            Пин: {{ activePinConfig?.pin || settingsPinKey }}
          </h3>
        </div>
        <div class="settings-modal__actions">
          <button class="settings-save-button" :disabled="settingsSavePending || !selectedController" @click="saveSettings">
            {{ settingsSavePending ? 'Сохранение...' : 'Сохранить' }}
          </button>
          <button type="button" class="settings-secondary-button" @click="closeSettingsModal">
            Закрыть
          </button>
        </div>
      </div>

      <p v-if="settingsPending" class="muted">Загрузка настроек...</p>
      <p v-if="settingsError" class="settings-error">{{ settingsError }}</p>

      <template v-if="settingsModal === 'controller'">
        <article class="pin-config-card">
          <div class="pin-config-grid pin-config-grid--controller">
            <label class="field">
              <span class="field__label">Имя контроллера</span>
              <input
                v-model="editableControllerName"
                class="field__input"
                type="text"
                placeholder="Контроллер теплицы"
              />
            </label>
            <label class="field">
              <span class="field__label">Описание</span>
              <input
                v-model="editableControllerDescription"
                class="field__input"
                type="text"
                placeholder="Основной контроллер полива"
              />
            </label>
            <label class="field">
              <span class="field__label">Интервал отправки, сек</span>
              <input v-model="editableSendIntervalSeconds" class="field__input" type="number" min="1" step="1" />
            </label>
          </div>
        </article>
      </template>

      <template v-else-if="activePinConfig">
        <article class="pin-config-card">
          <div class="pin-config-grid">
            <label class="field">
              <span class="field__label">Пин</span>
              <input :value="activePinConfig.pin" class="field__input" type="text" readonly />
            </label>
            <label class="field">
              <span class="field__label">Отображаемое имя</span>
              <input v-model="activePinConfig.label" class="field__input" type="text" placeholder="Освещение прихожей" />
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">Единица</span>
              <input v-model="activePinConfig.unit" class="field__input" type="text" placeholder="°C" />
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">Множитель</span>
              <input v-model="activePinConfig.multiplier" class="field__input" type="number" step="0.01" />
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">Смещение</span>
              <input v-model="activePinConfig.offset" class="field__input" type="number" step="0.01" />
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">Точность</span>
              <input v-model="activePinConfig.precision" class="field__input" type="number" min="0" step="1" />
            </label>
            <label class="field">
              <span class="field__label">Порядок</span>
              <input v-model="activePinConfig.sort_order" class="field__input" type="number" step="1" />
            </label>
            <label class="field field--checkbox">
              <input v-model="activePinConfig.show_on_dashboard" type="checkbox" />
              <span class="field__label">Показывать на дашборде</span>
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field field--checkbox">
              <input v-model="activePinConfig.show_on_chart" type="checkbox" />
              <span class="field__label">Показывать на графике</span>
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">График за последние часы</span>
              <input v-model="activePinConfig.chart_range_hours" class="field__input" type="number" min="1" step="1" />
            </label>
            <label v-if="isAnalogPin(activePinConfig.pin)" class="field">
              <span class="field__label">Интервал среднего значения, мин</span>
              <input
                v-model="activePinConfig.average_interval_minutes"
                class="field__input"
                type="number"
                min="1"
                step="1"
              />
            </label>
            <div v-if="isAnalogPin(activePinConfig.pin)" class="field field--action">
              <span class="field__label">История</span>
              <button
                type="button"
                class="settings-secondary-button settings-secondary-button--danger"
                :disabled="historyClearPendingPin === activePinConfig.pin"
                @click="clearAnalogPinHistory(activePinConfig.pin)"
              >
                {{ historyClearPendingPin === activePinConfig.pin ? 'Очистка...' : 'Очистить историю' }}
              </button>
            </div>
          </div>

          <div v-if="isDigitalPin(activePinConfig.pin)" class="pin-config-grid">
            <label class="field">
              <span class="field__label">Тип цифрового датчика</span>
              <select v-model="activePinConfig.digital_style" class="field__input">
                <option v-for="(preset, key) in digitalBadgePresets" :key="key" :value="key">
                  {{ preset.label }}
                </option>
              </select>
            </label>
            <label class="field">
              <span class="field__label">Текст при выключенном пине</span>
              <input v-model="activePinConfig.digital_off_text" class="field__input" type="text" placeholder="Выключен" />
            </label>
            <label class="field">
              <span class="field__label">Текст при включенном пине</span>
              <input v-model="activePinConfig.digital_on_text" class="field__input" type="text" placeholder="Включен" />
            </label>
            <label v-if="activePinConfig.digital_style === 'power'" class="field">
              <span class="field__label">Автоотключение, сек</span>
              <input
                :value="activePinConfig.power_on_duration_seconds ?? ''"
                class="field__input"
                type="number"
                min="1"
                step="1"
                placeholder="Без ограничения"
                @input="activePinConfig.power_on_duration_seconds = ($event.target as HTMLInputElement).value ? Math.max(1, Math.trunc(Number(($event.target as HTMLInputElement).value) || 1)) : null"
              />
            </label>
            <label v-if="activePinConfig.digital_style === 'power'" class="field field--checkbox">
              <input v-model="activePinConfig.invert_digital_logic" type="checkbox" />
              <span class="field__label">Инвертировать логику (0 = Включен)</span>
            </label>
          </div>

          <label v-else class="field">
            <span class="field__label">Текстовые значения JSON</span>
            <textarea v-model="activePinConfig.value_labels_text" class="field__textarea"></textarea>
          </label>
        </article>
      </template>
    </section>
  </div>
</template>
