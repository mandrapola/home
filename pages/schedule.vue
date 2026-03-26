<script setup lang="ts">
const { schedule, toggleSchedule } = useHomeState()
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Планировщик</p>
      <h2>Расписание</h2>
    </div>
    <p class="muted">Задайте время, когда дом выполняет сценарии автоматически</p>
  </section>

  <section class="stack-grid">
    <article v-for="item in schedule" :key="item.id" class="panel list-card">
      <div>
        <h3>{{ item.time }} · {{ item.action }}</h3>
        <p class="muted">{{ item.days }}</p>
      </div>
      <button class="switch" :class="{ 'switch--on': item.enabled }" @click="toggleSchedule(item.id)">
        {{ item.enabled ? 'Вкл' : 'Выкл' }}
      </button>
    </article>
  </section>
</template>
