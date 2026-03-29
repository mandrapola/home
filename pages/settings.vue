<script setup lang="ts">
interface TimeZoneResponse {
  time_zone: string
}

const COMMON_TIME_ZONES = [
  'Europe/Moscow',
  'UTC',
  'Europe/Berlin',
  'Europe/London',
  'Asia/Almaty',
  'Asia/Yekaterinburg',
  'Asia/Novosibirsk',
  'Asia/Vladivostok',
  'America/New_York',
  'America/Los_Angeles'
]

const selectedTimeZone = ref('Europe/Moscow')
const customTimeZone = ref('')
const loading = ref(false)
const saving = ref(false)
const statusMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

const activeTimeZone = computed(() => {
  const custom = customTimeZone.value.trim()
  return custom.length > 0 ? custom : selectedTimeZone.value
})

const loadTimeZone = async () => {
  loading.value = true
  statusMessage.value = null
  errorMessage.value = null

  try {
    const response = await $fetch<TimeZoneResponse>('/api/settings/timezone')
    if (COMMON_TIME_ZONES.includes(response.time_zone)) {
      selectedTimeZone.value = response.time_zone
      customTimeZone.value = ''
    } else {
      selectedTimeZone.value = 'Europe/Moscow'
      customTimeZone.value = response.time_zone
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Не удалось загрузить таймзону'
  } finally {
    loading.value = false
  }
}

onMounted(loadTimeZone)

const saveTimeZone = async () => {
  if (saving.value) {
    return
  }

  saving.value = true
  statusMessage.value = null
  errorMessage.value = null

  try {
    const response = await $fetch<TimeZoneResponse>('/api/settings/timezone', {
      method: 'PUT',
      body: {
        time_zone: activeTimeZone.value
      }
    })

    if (COMMON_TIME_ZONES.includes(response.time_zone)) {
      selectedTimeZone.value = response.time_zone
      customTimeZone.value = ''
    } else {
      customTimeZone.value = response.time_zone
    }

    statusMessage.value = `Таймзона сохранена: ${response.time_zone}`
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Не удалось сохранить таймзону'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Параметры</p>
      <h2>Настройки системы</h2>
    </div>
    <p class="muted">Глобальная таймзона для параметра «Текущее время» всех контроллеров</p>
  </section>

  <section class="stack-grid">
    <article class="panel list-card settings-timezone-card">
      <div class="settings-timezone-grid">
        <label class="field">
          <span class="field__label">Таймзона</span>
          <select v-model="selectedTimeZone" class="field__input">
            <option v-for="zone in COMMON_TIME_ZONES" :key="zone" :value="zone">
              {{ zone }}
            </option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Или ввести вручную (IANA)</span>
          <input v-model="customTimeZone" class="field__input" type="text" placeholder="Asia/Tokyo" />
        </label>
      </div>

      <div class="settings-modal__actions">
        <button class="settings-save-button" :disabled="saving || loading" @click="saveTimeZone">
          {{ saving ? 'Сохранение...' : 'Сохранить таймзону' }}
        </button>
      </div>

      <p v-if="statusMessage" class="muted">{{ statusMessage }}</p>
      <p v-if="errorMessage" class="settings-error">{{ errorMessage }}</p>
    </article>
  </section>
</template>

<style scoped>
.settings-timezone-card {
  align-items: stretch;
}

.settings-timezone-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(200px, 1fr));
  gap: 0.8rem;
}

@media (max-width: 980px) {
  .settings-timezone-grid {
    grid-template-columns: 1fr;
  }
}
</style>
