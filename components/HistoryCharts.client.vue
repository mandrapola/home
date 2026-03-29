<script setup lang="ts">
import { Chart } from 'chart.js/auto'

interface Reading {
  id: number | string
  pin: string
  value: number
  raw_value: number
  display_value: number
  display_text: string
  label: string
  unit: string | null
  show_on_chart: boolean
  chart_range_hours: number
  average_interval_minutes: number
  controller_id: number
  created_at: string
}

interface ChartView {
  pin: string
  label: string
  unit: string | null
  labels: string[]
  values: number[]
}

const props = defineProps<{
  history: Reading[]
}>()

const canvases = ref<Record<string, HTMLCanvasElement | null>>({})
const chartMap = new Map<string, Chart>()

const chartPalette: Record<string, { line: string; fill: string }> = {
  thermometer: { line: '#d66b2c', fill: 'rgba(214, 107, 44, 0.22)' },
  pressure: { line: '#2a6f97', fill: 'rgba(42, 111, 151, 0.22)' },
  humidity: { line: '#2d936c', fill: 'rgba(45, 147, 108, 0.22)' },
  air_temperature: { line: '#d66b2c', fill: 'rgba(214, 107, 44, 0.22)' },
  air_humidity: { line: '#2d936c', fill: 'rgba(45, 147, 108, 0.22)' }
}

const groupedCharts = computed<ChartView[]>(() => {
  const groups = new Map<string, Reading[]>()

  for (const row of props.history) {
    if (!groups.has(row.pin)) {
      groups.set(row.pin, [])
    }
    groups.get(row.pin)?.push(row)
  }

  return [...groups.entries()].map(([pin, rows]) => {
    const sorted = [...rows].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    )

    return {
      pin,
      label: sorted[0]?.label ?? pin,
      unit: sorted[0]?.unit ?? null,
      labels: sorted.map((item) => new Date(item.created_at).toLocaleTimeString('ru-RU')),
      values: sorted.map((item) => item.display_value)
    }
  })
})

const destroyCharts = () => {
  for (const chart of chartMap.values()) {
    chart.destroy()
  }
  chartMap.clear()
}

const renderCharts = async () => {
  await nextTick()
  destroyCharts()

  for (const chartConfig of groupedCharts.value) {
    const canvas = canvases.value[chartConfig.pin]
    if (!canvas) {
      continue
    }

    const colors = chartPalette[chartConfig.pin] ?? {
      line: '#177b52',
      fill: 'rgba(23, 123, 82, 0.2)'
    }

    const chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: chartConfig.labels,
        datasets: [
          {
            label: chartConfig.label,
            data: chartConfig.values,
            borderColor: colors.line,
            backgroundColor: colors.fill,
            fill: true,
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            ticks: {
              maxTicksLimit: 6
            }
          },
          y: {
            ticks: {
              callback(value) {
                return `${value} ${chartConfig.unit || ''}`.trim()
              }
            }
          }
        }
      }
    })

    chartMap.set(chartConfig.pin, chart)
  }
}

watch(groupedCharts, async () => {
  await renderCharts()
})

onMounted(async () => {
  await renderCharts()
})

onBeforeUnmount(() => {
  destroyCharts()
})
</script>

<template>
  <div v-if="groupedCharts.length === 0" class="muted">
    Нет данных для построения графиков.
  </div>

  <div v-else class="charts-grid">
    <article v-for="chart in groupedCharts" :key="chart.pin" class="chart-card">
      <div class="chart-card__head">
        <h4>{{ chart.label }}</h4>
        <p class="muted">{{ chart.unit || '' }}</p>
      </div>
      <div class="chart-card__canvas-wrap">
        <canvas :ref="(el) => (canvases[chart.pin] = el as HTMLCanvasElement | null)" />
      </div>
    </article>
  </div>
</template>
