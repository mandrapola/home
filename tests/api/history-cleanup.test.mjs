import test from 'node:test'
import assert from 'node:assert/strict'

const BASE_URL = process.env.API_BASE_URL || 'http://localhost:3000'

const request = async (path, options = {}) => {
  const response = await fetch(`${BASE_URL}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  })

  let body = null
  try {
    body = await response.json()
  } catch {
    body = null
  }

  return { response, body }
}

test('history cleanup works for lowercase analog-like pins', async () => {
  const controllerId = 1
  const tempPin = `air_temperature_test_${Date.now()}`
  const humidityPin = `air_humidity_test_${Date.now()}`

  const reportPayload = {
    controller_id: controllerId,
    readings: [
      { pin: tempPin, value: 22.7 },
      { pin: humidityPin, value: 48.1 }
    ]
  }

  const report = await request('/api/controller/report', {
    method: 'POST',
    body: JSON.stringify(reportPayload)
  })

  assert.equal(report.response.status, 200, `report status must be 200, got ${report.response.status}`)

  const tempDelete = await request(
    `/api/controllers/${controllerId}/pins/${encodeURIComponent(tempPin)}/history`,
    { method: 'DELETE' }
  )
  const humidityDelete = await request(
    `/api/controllers/${controllerId}/pins/${encodeURIComponent(humidityPin)}/history`,
    { method: 'DELETE' }
  )

  assert.equal(tempDelete.response.status, 200, `temp delete status must be 200, got ${tempDelete.response.status}`)
  assert.equal(
    humidityDelete.response.status,
    200,
    `humidity delete status must be 200, got ${humidityDelete.response.status}`
  )

  assert.equal(tempDelete.body?.ok, true, 'temp delete must return ok=true')
  assert.equal(humidityDelete.body?.ok, true, 'humidity delete must return ok=true')

  assert.ok(
    Number(tempDelete.body?.deleted) >= 1,
    `temp delete should remove at least one row, got ${String(tempDelete.body?.deleted)}`
  )
  assert.ok(
    Number(humidityDelete.body?.deleted) >= 1,
    `humidity delete should remove at least one row, got ${String(humidityDelete.body?.deleted)}`
  )

  // Second delete should be idempotent: already removed.
  const tempDeleteAgain = await request(
    `/api/controllers/${controllerId}/pins/${encodeURIComponent(tempPin)}/history`,
    { method: 'DELETE' }
  )
  const humidityDeleteAgain = await request(
    `/api/controllers/${controllerId}/pins/${encodeURIComponent(humidityPin)}/history`,
    { method: 'DELETE' }
  )

  assert.equal(tempDeleteAgain.response.status, 200)
  assert.equal(humidityDeleteAgain.response.status, 200)
  assert.equal(Number(tempDeleteAgain.body?.deleted), 0, 'second temp delete should remove 0 rows')
  assert.equal(Number(humidityDeleteAgain.body?.deleted), 0, 'second humidity delete should remove 0 rows')
})
