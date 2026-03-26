<script setup lang="ts">
const { scenes, toggleScene } = useHomeState()
</script>

<template>
  <section class="page-head">
    <div>
      <p class="eyebrow">Автоматизация</p>
      <h2>Сценарии</h2>
    </div>
    <p class="muted">Запускайте и настраивайте готовые режимы дома</p>
  </section>

  <section class="stack-grid">
    <article v-for="scene in scenes" :key="scene.id" class="panel list-card">
      <div>
        <h3>{{ scene.title }}</h3>
        <p class="muted">{{ scene.description }}</p>
      </div>
      <button class="switch" :class="{ 'switch--on': scene.enabled }" @click="toggleScene(scene.id)">
        {{ scene.enabled ? 'Активен' : 'Неактивен' }}
      </button>
    </article>
  </section>
</template>
