import fs from 'node:fs'
import fsp from 'node:fs/promises'
import path from 'node:path'

const TEST_FILE_NAME = '.rw-check.tmp'

export const isWritableDirectory = async (dirPath) => {
  try {
    const stats = await fsp.stat(dirPath)
    if (!stats.isDirectory()) {
      return false
    }

    await fsp.access(dirPath, fs.constants.R_OK | fs.constants.W_OK)
    const testFile = path.join(dirPath, TEST_FILE_NAME)
    await fsp.writeFile(testFile, String(Date.now()), 'utf8')
    await fsp.unlink(testFile)
    return true
  } catch {
    return false
  }
}
