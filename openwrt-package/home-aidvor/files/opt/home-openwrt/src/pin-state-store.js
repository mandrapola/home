const normalizePin = (pin) => String(pin).trim().toUpperCase()

const normalizeBit = (value) => (Number(value) > 0 ? 1 : 0)

export const createPinStateStore = (pins, invertedPins = new Set()) => {
  const state = new Map()
  const initial = Array.isArray(pins) ? pins : []
  const inverted = new Set(Array.from(invertedPins).map((pin) => normalizePin(pin)))

  const isInverted = (pin) => inverted.has(normalizePin(pin))
  const logicalToRaw = (pin, logicalValue) => {
    const logical = normalizeBit(logicalValue)
    if (!isInverted(pin)) {
      return logical
    }
    return logical === 1 ? 0 : 1
  }
  const rawToLogical = (pin, rawValue) => {
    const raw = normalizeBit(rawValue)
    if (!isInverted(pin)) {
      return raw
    }
    return raw === 1 ? 0 : 1
  }

  for (const pin of initial) {
    const normalizedPin = normalizePin(pin)
    const logicalValue = 0
    state.set(normalizedPin, {
      pin: normalizedPin,
      value: logicalValue,
      rawValue: logicalToRaw(normalizedPin, logicalValue),
      inverted: isInverted(normalizedPin),
      updatedAt: null,
      source: null
    })
  }

  return {
    hasPin(pin) {
      return state.has(normalizePin(pin))
    },
    set(pin, logicalValue, source = 'manual_ui') {
      const normalizedPin = normalizePin(pin)
      if (!state.has(normalizedPin)) {
        throw new Error(`Pin ${normalizedPin} is not configured.`)
      }

      const normalizedLogical = normalizeBit(logicalValue)
      const entry = {
        pin: normalizedPin,
        value: normalizedLogical,
        rawValue: logicalToRaw(normalizedPin, normalizedLogical),
        inverted: isInverted(normalizedPin),
        updatedAt: new Date().toISOString(),
        source: String(source || 'manual_ui')
      }
      state.set(normalizedPin, entry)
      return entry
    },
    setRaw(pin, rawValue, source = 'cloud') {
      return this.set(pin, rawToLogical(pin, rawValue), source)
    },
    setInvertedPins(pins) {
      const nextInverted = new Set(Array.from(pins ?? []).map((pin) => normalizePin(pin)))
      inverted.clear()
      for (const pin of nextInverted) {
        inverted.add(pin)
      }

      for (const [pin, entry] of state.entries()) {
        const logicalValue = normalizeBit(entry.value)
        state.set(pin, {
          ...entry,
          value: logicalValue,
          rawValue: logicalToRaw(pin, logicalValue),
          inverted: isInverted(pin)
        })
      }
    },
    applyDigitalOutputs(outputMap, source = 'cloud') {
      if (!outputMap || typeof outputMap !== 'object') {
        return
      }

      for (const [pin, value] of Object.entries(outputMap)) {
        const normalizedPin = normalizePin(pin)
        if (!state.has(normalizedPin)) {
          continue
        }
        this.setRaw(normalizedPin, value, source)
      }
    },
    toDigitalOutputs() {
      return Array.from(state.entries()).reduce((acc, [pin, entry]) => {
        acc[pin] = entry.rawValue > 0 ? 1 : 0
        return acc
      }, {})
    },
    list() {
      return Array.from(state.values())
    }
  }
}
