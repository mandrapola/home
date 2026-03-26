<script setup lang="ts">
const alertDoor = ref(true)
const alertWater = ref(true)
const vacationMode = ref(false)
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Параметры</p>
      <h2>Настройки системы</h2>
    </div>
    <p class="muted">Уведомления, безопасность и режимы энергосбережения</p>
  </section>

  <section class="stack-grid">
    <article class="panel list-card">
      <div>
        <h3>Уведомление при открытии двери</h3>
        <p class="muted">Push-уведомление при срабатывании датчика входа</p>
      </div>
      <button class="switch" :class="{ 'switch--on': alertDoor }" @click="alertDoor = !alertDoor">
        {{ alertDoor ? 'Вкл' : 'Выкл' }}
      </button>
    </article>

    <article class="panel list-card">
      <div>
        <h3>Контроль протечки воды</h3>
        <p class="muted">Аварийное отключение подачи воды и тревожный сигнал</p>
      </div>
      <button class="switch" :class="{ 'switch--on': alertWater }" @click="alertWater = !alertWater">
        {{ alertWater ? 'Вкл' : 'Выкл' }}
      </button>
    </article>

    <article class="panel list-card">
      <div>
        <h3>Режим отпуска</h3>
        <p class="muted">Минимальное энергопотребление и периодическая имитация присутствия</p>
      </div>
      <button class="switch" :class="{ 'switch--on': vacationMode }" @click="vacationMode = !vacationMode">
        {{ vacationMode ? 'Вкл' : 'Выкл' }}
      </button>
    </article>
  </section>
</template>
