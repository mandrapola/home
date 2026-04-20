export const sendJson = (res, statusCode, payload) => {
  const body = JSON.stringify(payload)
  res.writeHead(statusCode, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(body)
  })
  res.end(body)
}

export const notFound = (res) =>
  sendJson(res, 404, {
    error: 'not_found'
  })

export const badRequest = (res, message) =>
  sendJson(res, 400, {
    error: 'bad_request',
    message
  })

export const methodNotAllowed = (res, allowMethods) => {
  res.setHeader('allow', allowMethods.join(', '))
  return sendJson(res, 405, {
    error: 'method_not_allowed'
  })
}

export const collectBodyJson = async (req) =>
  new Promise((resolve, reject) => {
    const chunks = []
    req.on('data', (chunk) => chunks.push(chunk))
    req.on('end', () => {
      if (chunks.length === 0) {
        return resolve({})
      }

      try {
        const text = Buffer.concat(chunks).toString('utf8')
        resolve(JSON.parse(text))
      } catch {
        reject(new Error('Invalid JSON body'))
      }
    })
    req.on('error', reject)
  })

export const collectBodyText = async (req) =>
  new Promise((resolve, reject) => {
    const chunks = []
    req.on('data', (chunk) => chunks.push(chunk))
    req.on('end', () => {
      if (chunks.length === 0) {
        return resolve('')
      }
      resolve(Buffer.concat(chunks).toString('utf8'))
    })
    req.on('error', reject)
  })

export const sendText = (
  res,
  statusCode,
  body,
  contentType = 'application/json; charset=utf-8',
  extraHeaders = {}
) => {
  const text = String(body ?? '')
  res.writeHead(statusCode, {
    'content-type': contentType,
    'content-length': Buffer.byteLength(text),
    ...extraHeaders
  })
  res.end(text)
}
