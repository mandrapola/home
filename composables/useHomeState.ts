export type DeviceType = 'light' | 'climate' | 'security' | 'appliance'

export interface Device {
  id: string
  name: string
  room: string
  type: DeviceType
  active: boolean
  level?: number
  note?: string
}

export interface Scene {
  id: string
  title: string
  description: string
  enabled: boolean
}

export interface ScheduleItem {
  id: string
  time: string
  action: string
  days: string
  enabled: boolean
}

const useSmartHomeSingleton = () => {
  const devices = useState<Device[]>('devices', () => [
    { id: 'l1', name: 'Люстра', room: 'Гостиная', type: 'light', active: true, level: 72, note: 'Тёплый свет' },
    { id: 'c1', name: 'Кондиционер', room: 'Спальня', type: 'climate', active: false, level: 21, note: '21°C' },
    { id: 's1', name: 'Камера входа', room: 'Прихожая', type: 'security', active: true, note: 'Режим записи' },
    { id: 'a1', name: 'Кофемашина', room: 'Кухня', type: 'appliance', active: false, note: 'Ожидание' },
    { id: 'l2', name: 'Лента RGB', room: 'Кабинет', type: 'light', active: true, level: 45, note: 'Сцена Focus' }
  ])

  const scenes = useState<Scene[]>('scenes', () => [
    { id: 'sc1', title: 'Доброе утро', description: 'Открыть шторы, запустить кофемашину, свет 40%', enabled: true },
    { id: 'sc2', title: 'Кино', description: 'Свет 15%, температура 22°C, без уведомлений', enabled: false },
    { id: 'sc3', title: 'Вне дома', description: 'Включить охрану, отключить розетки, эко-режим климата', enabled: true }
  ])

  const schedule = useState<ScheduleItem[]>('schedule', () => [
    { id: 'sch1', time: '07:00', action: 'Сцена «Доброе утро»', days: 'Пн-Пт', enabled: true },
    { id: 'sch2', time: '23:30', action: 'Сцена «Вне дома»', days: 'Ежедневно', enabled: false },
    { id: 'sch3', time: '19:00', action: 'Полив растений', days: 'Вт, Чт, Сб', enabled: true }
  ])

  const energyToday = useState<number>('energyToday', () => 18.4)
  const humidity = useState<number>('humidity', () => 47)
  const temperature = useState<number>('temperature', () => 22)

  const toggleDevice = (id: string) => {
    devices.value = devices.value.map((device) =>
      device.id === id ? { ...device, active: !device.active } : device
    )
  }

  const updateDeviceLevel = (id: string, level: number) => {
    devices.value = devices.value.map((device) =>
      device.id === id ? { ...device, level } : device
    )
  }

  const toggleScene = (id: string) => {
    scenes.value = scenes.value.map((scene) =>
      scene.id === id ? { ...scene, enabled: !scene.enabled } : scene
    )
  }

  const toggleSchedule = (id: string) => {
    schedule.value = schedule.value.map((item) =>
      item.id === id ? { ...item, enabled: !item.enabled } : item
    )
  }

  return {
    devices,
    scenes,
    schedule,
    energyToday,
    humidity,
    temperature,
    toggleDevice,
    updateDeviceLevel,
    toggleScene,
    toggleSchedule
  }
}

export const useHomeState = () => useSmartHomeSingleton()
