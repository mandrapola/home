<script setup lang="ts">
interface Controller {
  id: number
  name: string
}

interface Scenario {
  id: number
  controller_id: number
  name: string
  source_pin: string
  operator: 'gt' | 'gte' | 'lt' | 'lte'
  threshold: number
  hysteresis: number
  target_pin: string
  value_when_true: 0 | 1
  value_when_false: 0 | 1
  priority: number
  enabled: boolean
  current_state: 0 | 1
}

interface ScenarioForm {
  id: number | null
  controller_id: number | null
  name: string
  source_pin: string
  operator: 'gt' | 'gte' | 'lt' | 'lte'
  threshold: number
  hysteresis: number
  target_pin: string
  value_when_true: 0 | 1
  value_when_false: 0 | 1
  priority: number
  enabled: boolean
}

interface TargetPinGroup {
  key: string
  controllerId: number
  targetPin: string
  displayName: string
  state: 0 | 1
  scenarios: Scenario[]
}

interface PinConfigOption {
  pin: string
  label: string
  sortOrder: number
  invertDigitalLogic: boolean
  averageIntervalMinutes: number
}

const { data: controllersData } = await useFetch<{ controllers: Controller[] }>('/api/controllers', {
  default: () => ({ controllers: [] })
})

const scenarios = ref<Scenario[]>([])
const pinOptionsByController = ref<Record<number, PinConfigOption[]>>({})
const loading = ref(false)
const initialLoading = ref(true)
const errorMessage = ref<string | null>(null)
const AUTO_REFRESH_MS = 5000
let autoRefreshTimer: ReturnType<typeof setInterval> | null = null

const isModalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const modalSaving = ref(false)
const modalDeleting = ref(false)
const formError = ref<string | null>(null)
const editControllerId = ref<number | null>(null)

const expandedTargetPins = ref<Record<string, boolean>>({})

const formState = reactive<ScenarioForm>({
  id: null,
  controller_id: null,
  name: 'Новый сценарий',
  source_pin: 'air_temperature',
  operator: 'gt',
  threshold: 26,
  hysteresis: 0.5,
  target_pin: 'D6',
  value_when_true: 1,
  value_when_false: 0,
  priority: 100,
  enabled: true
})

const controllerMap = computed(() =>
  new Map(controllersData.value.controllers.map((controller) => [controller.id, controller]))
)

const targetPinGroups = computed<TargetPinGroup[]>(() => {
  const groups = new Map<string, Scenario[]>()

  for (const scenario of scenarios.value) {
    const key = `${scenario.controller_id}:${scenario.target_pin.trim().toUpperCase()}`
    if (!key) {
      continue
    }

    const list = groups.get(key)
    if (list) {
      list.push(scenario)
    } else {
      groups.set(key, [scenario])
    }
  }

  return Array.from(groups.entries()).map(([groupKey, list]) => {
    const sorted = [...list].sort((a, b) => (a.priority - b.priority) || (a.id - b.id))
    const activeScenario = sorted.find((scenario) => scenario.enabled)
    const state = activeScenario ? (activeScenario.current_state > 0 ? 1 : 0) : 0
    const controllerId = Number(groupKey.split(':')[0])
    const targetPin = sorted[0]?.target_pin?.trim() || ''
    const controllerName =
      controllerMap.value.get(controllerId)?.name?.trim() || `controller-${controllerId}`

    return {
      key: groupKey,
      controllerId,
      targetPin,
      displayName: `${controllerName} / ${targetPin}`,
      state,
      scenarios: sorted
    }
  }).sort((a, b) => a.displayName.localeCompare(b.displayName, undefined, { sensitivity: 'base', numeric: true }))
})

const targetPinSelectOptions = computed(() => {
  const options: Array<{ value: string; label: string }> = []

  for (const controller of controllersData.value.controllers) {
    const pinOptions = pinOptionsByController.value[controller.id] ?? []
    for (const option of pinOptions.filter((item) => /^D\d+$/i.test(item.pin))) {
      options.push({
        value: `${controller.id}:${option.pin.toUpperCase()}`,
        label: `${controller.name} + ${option.label || option.pin}`
      })
    }
  }

  return options
})

const sourcePinSelectOptions = computed(() => {
  const options: Array<{ value: string; label: string }> = []

  for (const controller of controllersData.value.controllers) {
    const controllerPrefix = `controller:${controller.id}:`
    options.push({
      value: `${controllerPrefix}current_time`,
      label: `${controller.name} + Текущее время`
    })

    const pinOptions = pinOptionsByController.value[controller.id] ?? []
    for (const option of pinOptions) {
      const isAnalog = /^A\d+$/i.test(option.pin) || ['air_temperature', 'air_humidity'].includes(option.pin.trim().toLowerCase())
      if (!isAnalog) {
        continue
      }

      const intervalMinutes = Math.max(1, Math.trunc(Number(option.averageIntervalMinutes) || 5))
      options.push({
        value: `${controllerPrefix}avg_pin:${option.pin}`,
        label: `${controller.name} + ${option.label || option.pin} за последние ${intervalMinutes} мин`
      })
    }

    for (const option of pinOptions.filter((item) => /^D\d+$/i.test(item.pin))) {
      options.push({
        value: `${controllerPrefix}pin_on_seconds_24h:${option.pin.toUpperCase()}`,
        label: `${controller.name} + Время включения ${option.label || option.pin} за 24ч (сек)`
      })
      options.push({
        value: `${controllerPrefix}pin_state:${option.pin.toUpperCase()}`,
        label: `${controller.name} + Состояние ${option.label || option.pin} (с учетом инверсии)`
      })
    }
  }

  return options
})

const sourcePinLabelByValue = computed(() =>
  new Map(sourcePinSelectOptions.value.map((option) => [option.value, option.label]))
)

const sourcePinDisplayLabel = (sourcePin: string) =>
  sourcePinLabelByValue.value.get(sourcePin) ?? sourcePin

const targetPinSelectValue = computed<string>({
  get: () => {
    if (!formState.controller_id || !formState.target_pin) {
      return ''
    }
    return `${formState.controller_id}:${formState.target_pin.toUpperCase()}`
  },
  set: (value) => {
    const [controllerIdText, pinRaw] = String(value).split(':')
    const controllerId = Number(controllerIdText)
    if (!Number.isInteger(controllerId) || controllerId <= 0 || !pinRaw) {
      return
    }
    formState.controller_id = controllerId
    formState.target_pin = pinRaw.toUpperCase()
  }
})

const normalizeSourceKey = (sourcePin: string) =>
  sourcePin.replace(/^controller:\d+:/, '').trim()

const sourceControllerIdFromSourcePin = (sourcePin: string) => {
  const match = String(sourcePin).trim().match(/^controller:(\d+):/)
  if (!match) {
    return formState.controller_id
  }
  const id = Number(match[1])
  return Number.isInteger(id) && id > 0 ? id : formState.controller_id
}

const formatSecondsAsTime = (totalSeconds: number) => {
  const safe = Math.max(0, Math.trunc(totalSeconds || 0)) % 86400
  const hours = String(Math.floor(safe / 3600)).padStart(2, '0')
  const minutes = String(Math.floor((safe % 3600) / 60)).padStart(2, '0')
  const seconds = String(safe % 60).padStart(2, '0')
  return `${hours}:${minutes}:${seconds}`
}

const parseTimeToSeconds = (value: string) => {
  const match = value.trim().match(/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/)
  if (!match) {
    return null
  }

  const hours = Number(match[1])
  const minutes = Number(match[2])
  const seconds = Number(match[3] ?? 0)
  return hours * 3600 + minutes * 60 + seconds
}

const isDigitalSource = (sourcePin: string) => {
  const normalized = normalizeSourceKey(sourcePin)
  return /^D\d+$/i.test(normalized) || normalized.startsWith('pin_state:')
}
const isTimeSource = (sourcePin: string) => normalizeSourceKey(sourcePin) === 'current_time'

const getPinConfig = (pin: string, controllerId: number | null) =>
  (controllerId
    ? pinOptionsByController.value[controllerId] ?? []
    : []
  ).find((option) => option.pin.toUpperCase() === pin.trim().toUpperCase()) ?? null

const isInvertedPin = (pin: string, controllerId: number | null) =>
  Boolean(getPinConfig(pin, controllerId)?.invertDigitalLogic)

const isDigitalSourceSelected = computed(() => isDigitalSource(formState.source_pin))
const isTimeSourceSelected = computed(() => isTimeSource(formState.source_pin))

const timeThresholdText = computed<string>({
  get: () => formatSecondsAsTime(Number(formState.threshold)),
  set: (value) => {
    const parsed = parseTimeToSeconds(value)
    if (parsed != null) {
      formState.threshold = parsed
    }
  }
})

const digitalConditionOn = computed<boolean>({
  get: () => {
    const rawOn = formState.operator === 'gt' || formState.operator === 'gte'
    const normalizedSource = normalizeSourceKey(formState.source_pin)

    if (normalizedSource.startsWith('pin_state:')) {
      return rawOn
    }
    const sourceControllerId = sourceControllerIdFromSourcePin(formState.source_pin)
    if (/^D\d+$/i.test(normalizedSource) && isInvertedPin(normalizedSource, sourceControllerId)) {
      return !rawOn
    }

    return rawOn
  },
  set: (enabled) => {
    const normalizedSource = normalizeSourceKey(formState.source_pin)
    const sourceControllerId = sourceControllerIdFromSourcePin(formState.source_pin)
    const rawOn =
      /^D\d+$/i.test(normalizedSource) && isInvertedPin(normalizedSource, sourceControllerId)
        ? !enabled
        : enabled
    formState.operator = rawOn ? 'gt' : 'lte'
    formState.threshold = 0
    formState.hysteresis = 0
  }
})

const resultOnState = computed<boolean>({
  get: () => {
    const targetInvert = isInvertedPin(formState.target_pin, formState.controller_id)
    const rawOn = Number(formState.value_when_true) > 0
    return targetInvert ? !rawOn : rawOn
  },
  set: (enabled) => {
    const targetInvert = isInvertedPin(formState.target_pin, formState.controller_id)
    const rawOn = targetInvert ? !enabled : enabled
    formState.value_when_true = rawOn ? 1 : 0
    formState.value_when_false = rawOn ? 0 : 1
  }
})

const loadData = async () => {
  if (loading.value) {
    return
  }

  const controllers = controllersData.value.controllers
  if (controllers.length === 0) {
    scenarios.value = []
    pinOptionsByController.value = {}
    initialLoading.value = false
    return
  }

  loading.value = true
  errorMessage.value = null

  try {
    const [scenarioResponses, settingsResponses] = await Promise.all([
      Promise.all(
        controllers.map((controller) =>
          $fetch<{ scenarios: Scenario[] }>(`/api/controllers/${controller.id}/scenarios`)
        )
      ),
      Promise.all(
        controllers.map((controller) =>
          $fetch<{ pinConfigs: Array<{ pin: string; digital_style: string; label: string; sort_order?: number; invert_digital_logic?: boolean; average_interval_minutes?: number }> }>(
            `/api/controllers/${controller.id}/settings`
          )
        )
      )
    ])

    scenarios.value = scenarioResponses.flatMap((response) => response.scenarios)
    pinOptionsByController.value = Object.fromEntries(
      settingsResponses.map((response, index) => {
        const controllerId = controllers[index].id
        const items = response.pinConfigs
          .map((config) => ({
            pin: config.pin,
            label: config.label,
            sortOrder: Number(config.sort_order ?? 0),
            invertDigitalLogic: Boolean(config.invert_digital_logic),
            averageIntervalMinutes: Math.max(1, Math.trunc(Number(config.average_interval_minutes ?? 5)))
          }))
          .sort(
            (a, b) =>
              (a.sortOrder - b.sortOrder) ||
              a.pin.localeCompare(b.pin, undefined, { numeric: true, sensitivity: 'base' })
          )
        return [controllerId, items]
      })
    ) as Record<number, PinConfigOption[]>

    const nextExpanded: Record<string, boolean> = {}
    for (const scenario of scenarios.value) {
      const key = `${scenario.controller_id}:${scenario.target_pin.trim().toUpperCase()}`
      if (expandedTargetPins.value[key]) {
        nextExpanded[key] = true
      }
    }
    expandedTargetPins.value = nextExpanded
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Не удалось загрузить сценарии'
  } finally {
    loading.value = false
    initialLoading.value = false
  }
}

watch(() => controllersData.value.controllers.map((controller) => controller.id).join(','), () => {
  loadData()
}, { immediate: true })

onMounted(() => {
  autoRefreshTimer = setInterval(() => {
    if (controllersData.value.controllers.length === 0 || modalSaving.value || modalDeleting.value) {
      return
    }
    loadData()
  }, AUTO_REFRESH_MS)
})

onBeforeUnmount(() => {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer)
    autoRefreshTimer = null
  }
})

const resetFormState = () => {
  formState.id = null
  formState.controller_id = null
  formState.name = 'Новый сценарий'
  formState.source_pin = 'air_temperature'
  formState.operator = 'gt'
  formState.threshold = 26
  formState.hysteresis = 0.5
  formState.target_pin = 'D6'
  formState.value_when_true = 1
  formState.value_when_false = 0
  formState.priority = 100
  formState.enabled = true
}

const openCreateModal = () => {
  resetFormState()
  editControllerId.value = null
  if (targetPinSelectOptions.value.length > 0) {
    targetPinSelectValue.value = targetPinSelectOptions.value[0].value
  }
  if (sourcePinSelectOptions.value.length > 0) {
    formState.source_pin = sourcePinSelectOptions.value[0].value
  }
  modalMode.value = 'create'
  formError.value = null
  isModalOpen.value = true
}

const openEditModal = (scenario: Scenario) => {
  editControllerId.value = scenario.controller_id
  formState.id = scenario.id
  formState.controller_id = scenario.controller_id
  formState.name = scenario.name
  formState.source_pin = scenario.source_pin
  formState.operator = scenario.operator
  formState.threshold = scenario.threshold
  formState.hysteresis = scenario.hysteresis
  formState.target_pin = scenario.target_pin
  formState.value_when_true = scenario.value_when_true
  formState.value_when_false = scenario.value_when_false
  formState.priority = scenario.priority
  formState.enabled = scenario.enabled

  modalMode.value = 'edit'
  formError.value = null
  isModalOpen.value = true
}

const closeModal = () => {
  if (modalSaving.value || modalDeleting.value) {
    return
  }
  isModalOpen.value = false
  formError.value = null
}

const makeScenarioPayload = () => {
  const digitalSource = isDigitalSourceSelected.value
  const timeSource = isTimeSourceSelected.value
  const normalizedSource = normalizeSourceKey(formState.source_pin)
  const sourceControllerId = sourceControllerIdFromSourcePin(formState.source_pin)
  const rawOnForCondition =
    digitalSource &&
    /^D\d+$/i.test(normalizedSource) &&
    isInvertedPin(normalizedSource, sourceControllerId)
      ? !digitalConditionOn.value
      : digitalConditionOn.value
  const operator = digitalSource
    ? (rawOnForCondition ? 'gt' : 'lte')
    : timeSource
      ? (formState.operator === 'lt' ? 'lt' : 'gt')
      : formState.operator
  const threshold = digitalSource
    ? 0
    : timeSource
      ? (parseTimeToSeconds(timeThresholdText.value) ?? Number(formState.threshold))
      : Number(formState.threshold)
  const hysteresis = digitalSource || timeSource ? 0 : Math.max(0, Number(formState.hysteresis))

  return {
    name: formState.name.trim(),
    source_pin: formState.source_pin.trim(),
    operator,
    threshold,
    hysteresis,
    target_pin: formState.target_pin.trim(),
    value_when_true: Number(formState.value_when_true) > 0 ? 1 : 0,
    value_when_false: Number(formState.value_when_true) > 0 ? 0 : 1,
    priority: Math.trunc(Number(formState.priority) || 100),
    enabled: Boolean(formState.enabled)
  }
}

const saveScenario = async () => {
  if (!formState.controller_id) {
    return
  }

  modalSaving.value = true
  formError.value = null

  try {
    const payload = makeScenarioPayload()

    if (modalMode.value === 'create') {
      await $fetch(`/api/controllers/${formState.controller_id}/scenarios`, {
        method: 'POST',
        body: payload
      })
    } else {
      if (!formState.id) {
        throw new Error('Scenario id is required')
      }
      if (!editControllerId.value || formState.controller_id !== editControllerId.value) {
        throw new Error('Для смены контроллера создайте новый сценарий и удалите текущий')
      }

      await $fetch(`/api/controllers/${formState.controller_id}/scenarios/${formState.id}`, {
        method: 'PUT',
        body: payload
      })
    }

    await loadData()
    isModalOpen.value = false
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Не удалось сохранить сценарий'
  } finally {
    modalSaving.value = false
  }
}

const deleteScenario = async () => {
  if (!formState.controller_id || !formState.id) {
    return
  }

  modalDeleting.value = true
  formError.value = null

  try {
    await $fetch(`/api/controllers/${formState.controller_id}/scenarios/${formState.id}`, {
      method: 'DELETE'
    })

    await loadData()
    isModalOpen.value = false
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Не удалось удалить сценарий'
  } finally {
    modalDeleting.value = false
  }
}

const toggleTargetPin = (key: string) => {
  expandedTargetPins.value[key] = !expandedTargetPins.value[key]
}

const isExpanded = (key: string) => Boolean(expandedTargetPins.value[key])

const lampIcon = (state: number) => (state > 0 ? '💡' : '◯')
const lampLabel = (state: number) => (state > 0 ? '1' : '0')

const scenarioConditionText = (scenario: Scenario) => {
  const operatorMap: Record<Scenario['operator'], string> = {
    gt: '>',
    gte: '>=',
    lt: '<',
    lte: '<='
  }

  if (isDigitalSource(scenario.source_pin)) {
    const rawOn = scenario.operator === 'gt' || scenario.operator === 'gte'
    const normalizedSource = normalizeSourceKey(scenario.source_pin)
    const digitalOn =
      /^D\d+$/i.test(normalizedSource) && isInvertedPin(normalizedSource, scenario.controller_id)
        ? !rawOn
        : rawOn
    return `${sourcePinDisplayLabel(scenario.source_pin)} = ${digitalOn ? 'Вкл' : 'Выкл'}`
  }

  if (isTimeSource(scenario.source_pin)) {
    const operator = scenario.operator === 'lt' ? '<' : '>'
    return `${sourcePinDisplayLabel(scenario.source_pin)} ${operator} ${formatSecondsAsTime(Number(scenario.threshold))}`
  }

  return `${sourcePinDisplayLabel(scenario.source_pin)} ${operatorMap[scenario.operator]} ${scenario.threshold}`
}
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Автоматизация</p>
      <h2>Сценарии</h2>
    </div>
    <div class="dash-head-meta">
      <button class="settings-save-button" :disabled="controllersData.controllers.length === 0" @click="openCreateModal">
        Добавить сценарий
      </button>
    </div>
  </section>

  <section class="panel">
    <p v-if="initialLoading" class="muted">Загрузка сценариев...</p>
    <p v-if="errorMessage" class="settings-error">{{ errorMessage }}</p>

    <div v-if="!initialLoading && targetPinGroups.length > 0" class="scenes-groups">
      <div class="scenes-groups__head">
        <span>Название</span>
        <span>Состояние</span>
      </div>

      <article v-for="group in targetPinGroups" :key="group.key" class="scenes-group-card">
        <button class="scenes-group-row" type="button" @click="toggleTargetPin(group.key)">
          <span class="scenes-group-row__title">
            <span class="scenes-group-row__chevron">{{ isExpanded(group.key) ? '▾' : '▸' }}</span>
            {{ group.displayName }}
          </span>
          <span class="scenes-state" :class="{ 'scenes-state--on': group.state > 0, 'scenes-state--off': group.state <= 0 }">
            <span>{{ lampIcon(group.state) }}</span>
            <span>{{ lampLabel(group.state) }}</span>
          </span>
        </button>

        <div v-if="isExpanded(group.key)" class="scenes-scenarios">
          <div class="scenes-scenarios__head">
            <span>Сценарий</span>
            <span>Условие</span>
            <span>Состояние</span>
            <span>Действие</span>
          </div>

          <div v-for="scenario in group.scenarios" :key="scenario.id" class="scenes-scenario-row">
            <span>{{ scenario.name }}</span>
            <span>{{ scenarioConditionText(scenario) }}</span>
            <span class="scenes-state" :class="{ 'scenes-state--on': scenario.current_state > 0, 'scenes-state--off': scenario.current_state <= 0 }">
              <span>{{ lampIcon(scenario.current_state) }}</span>
              <span>{{ lampLabel(scenario.current_state) }}</span>
            </span>
            <span>
              <button class="settings-secondary-button" type="button" @click="openEditModal(scenario)">
                Редактировать
              </button>
            </span>
          </div>
        </div>
      </article>
    </div>

    <p v-if="!initialLoading && targetPinGroups.length === 0" class="muted">Сценарии не настроены</p>
  </section>

  <div v-if="isModalOpen" class="settings-modal-overlay" @click.self="closeModal">
    <section class="settings-modal panel">
      <header class="panel-head">
        <div>
          <p class="eyebrow">Сценарии</p>
          <h3>{{ modalMode === 'create' ? 'Добавить сценарий' : 'Редактировать сценарий' }}</h3>
        </div>
        <div class="settings-modal__actions">
          <button class="settings-save-button" :disabled="modalSaving || modalDeleting" @click="saveScenario">
            {{ modalSaving ? 'Сохранение...' : 'Сохранить' }}
          </button>
          <button class="settings-secondary-button" :disabled="modalSaving || modalDeleting" @click="closeModal">
            Закрыть
          </button>
          <button
            v-if="modalMode === 'edit'"
            class="settings-secondary-button settings-secondary-button--danger"
            :disabled="modalSaving || modalDeleting"
            @click="deleteScenario"
          >
            {{ modalDeleting ? 'Удаление...' : 'Удалить' }}
          </button>
        </div>
      </header>

      <p v-if="formError" class="settings-error">{{ formError }}</p>

      <div class="scenario-form-layout">
        <section class="scenario-form-column">
          <h4 class="scenario-form-column__title">Параметр и условие</h4>
          <label class="field">
            <span class="field__label">Название</span>
            <input v-model="formState.name" class="field__input" type="text" />
          </label>
          <label class="field">
            <span class="field__label">Источник (пин)</span>
            <select v-model="formState.source_pin" class="field__input">
              <option v-for="option in sourcePinSelectOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
              <option v-if="!sourcePinSelectOptions.some((option) => option.value === formState.source_pin)" :value="formState.source_pin">
                {{ formState.source_pin }}
              </option>
            </select>
          </label>
          <div v-if="isDigitalSourceSelected" class="field">
            <span class="field__label">Условие (состояние)</span>
            <label class="field field--checkbox">
              <input v-model="digitalConditionOn" type="checkbox" />
              <span class="field__label">{{ digitalConditionOn ? 'Вкл' : 'Выкл' }}</span>
            </label>
          </div>
          <label v-else class="field">
            <span class="field__label">Оператор</span>
            <select v-model="formState.operator" class="field__input">
              <option value="gt">></option>
              <option v-if="!isTimeSourceSelected" value="gte">>=</option>
              <option value="lt"><</option>
              <option v-if="!isTimeSourceSelected" value="lte"><=</option>
            </select>
          </label>
          <label v-if="!isDigitalSourceSelected && !isTimeSourceSelected" class="field">
            <span class="field__label">Порог</span>
            <input v-model="formState.threshold" class="field__input" type="number" step="0.1" />
          </label>
          <label v-if="!isDigitalSourceSelected && isTimeSourceSelected" class="field">
            <span class="field__label">Время</span>
            <input v-model="timeThresholdText" class="field__input" type="time" step="1" />
          </label>
          <label v-if="!isDigitalSourceSelected && !isTimeSourceSelected" class="field">
            <span class="field__label">Гистерезис</span>
            <input v-model="formState.hysteresis" class="field__input" type="number" min="0" step="0.1" />
          </label>
        </section>

        <section class="scenario-form-column">
          <h4 class="scenario-form-column__title">Пин и результат</h4>
          <label class="field">
            <span class="field__label">Целевой пин</span>
            <select v-model="targetPinSelectValue" class="field__input">
              <option v-for="option in targetPinSelectOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
              <option
                v-if="targetPinSelectValue && !targetPinSelectOptions.some((option) => option.value === targetPinSelectValue)"
                :value="targetPinSelectValue"
              >
                {{ targetPinSelectValue }}
              </option>
            </select>
          </label>
          <div class="field">
            <span class="field__label">Действие при TRUE</span>
            <label class="field field--checkbox">
              <input v-model="resultOnState" type="checkbox" />
              <span class="field__label">Включить</span>
            </label>
          </div>
          <label class="field">
            <span class="field__label">Приоритет (меньше = важнее)</span>
            <input v-model="formState.priority" class="field__input" type="number" step="1" />
          </label>
          <label class="field field--checkbox">
            <input v-model="formState.enabled" type="checkbox" />
            <span class="field__label">Сценарий включен</span>
          </label>
        </section>
      </div>
    </section>
  </div>
</template>

<style scoped>
.scenario-form-layout {
  display: grid;
  grid-template-columns: repeat(2, minmax(260px, 1fr));
  gap: 1rem;
}

.scenario-form-column {
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 0.85rem;
  background: var(--surface-soft);
  display: grid;
  gap: 0.7rem;
}

.scenario-form-column__title {
  margin: 0;
  font-size: 0.92rem;
}

.scenes-groups {
  display: grid;
  gap: 0.8rem;
}

.scenes-groups__head,
.scenes-scenarios__head,
.scenes-scenario-row {
  display: grid;
  grid-template-columns: 1fr 220px;
  align-items: center;
  gap: 0.75rem;
}

.scenes-groups__head {
  padding: 0 0.7rem;
  color: var(--muted);
  font-size: 0.82rem;
}

.scenes-group-card {
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-soft);
  overflow: hidden;
}

.scenes-group-row {
  width: 100%;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
  padding: 0.8rem 0.85rem;
  display: grid;
  grid-template-columns: 1fr 220px;
  align-items: center;
  gap: 0.75rem;
}

.scenes-group-row__title {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 700;
}

.scenes-group-row__chevron {
  color: var(--muted);
  min-width: 0.8rem;
}

.scenes-state {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  width: fit-content;
  min-width: 90px;
  border-radius: 999px;
  padding: 0.25rem 0.65rem;
  font-weight: 700;
}

.scenes-state--on {
  color: var(--accent);
  background: rgba(23, 123, 82, 0.12);
}

.scenes-state--off {
  color: var(--danger);
  background: rgba(176, 64, 63, 0.12);
}

.scenes-scenarios {
  border-top: 1px solid var(--line);
  padding: 0.6rem 0.85rem 0.8rem;
  display: grid;
  gap: 0.55rem;
}

.scenes-scenarios__head,
.scenes-scenario-row {
  grid-template-columns: 1.1fr 1fr 180px 170px;
}

.scenes-scenarios__head {
  color: var(--muted);
  font-size: 0.8rem;
}

.scenes-scenario-row {
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 0.55rem 0.65rem;
  background: var(--surface);
}

@media (max-width: 980px) {
  .scenario-form-layout {
    grid-template-columns: 1fr;
  }

  .scenes-groups__head,
  .scenes-group-row {
    grid-template-columns: 1fr 160px;
  }

  .scenes-scenarios__head,
  .scenes-scenario-row {
    grid-template-columns: 1fr;
  }

  .scenes-scenario-row {
    gap: 0.4rem;
  }
}
</style>
