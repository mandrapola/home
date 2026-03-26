<template>
  <div class="shell">
    <aside class="sidebar">
      <div class="brand">
        <p class="brand__label">Smart Home</p>
        <h1 class="brand__title">Pulse Control</h1>
      </div>

      <nav class="nav">
        <NuxtLink to="/" class="nav__link" active-class="nav__link--active">Дашборд</NuxtLink>
        <NuxtLink to="/scenes" class="nav__link" active-class="nav__link--active">Сценарии</NuxtLink>
        <NuxtLink to="/schedule" class="nav__link" active-class="nav__link--active">Расписание</NuxtLink>
        <NuxtLink to="/settings" class="nav__link" active-class="nav__link--active">Настройки</NuxtLink>
      </nav>

      <div class="sidebar__footer">
        <p>Дом: <strong>Лесная 14</strong></p>
        <p>Сеть: <span class="online">онлайн</span></p>
      </div>
    </aside>

    <main class="content">
      <NuxtPage />
    </main>
  </div>
</template>
