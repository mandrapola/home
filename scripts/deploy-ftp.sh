#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${ROOT_DIR}/php-app"

if ! command -v lftp >/dev/null 2>&1; then
  echo "Ошибка: нужен lftp. Установите: sudo apt install lftp"
  exit 1
fi

DEPLOY_ENV="${1:-${ROOT_DIR}/deploy/ftp.env}"
if [[ ! -f "${DEPLOY_ENV}" ]]; then
  echo "Ошибка: не найден файл ${DEPLOY_ENV}"
  echo "Создайте его по шаблону deploy/ftp.env.example"
  exit 1
fi

# shellcheck disable=SC1090
source "${DEPLOY_ENV}"

: "${FTP_HOST:?FTP_HOST не задан}"
: "${FTP_USER:?FTP_USER не задан}"
: "${FTP_PASS:?FTP_PASS не задан}"
: "${FTP_REMOTE_DIR:?FTP_REMOTE_DIR не задан}"

FTP_PORT="${FTP_PORT:-21}"
FTP_SSL="${FTP_SSL:-false}"

if [[ "${FTP_SSL}" == "true" ]]; then
  if [[ "${FTP_SSL_VERIFY:-true}" == "false" ]]; then
    SSL_SETTINGS="set ssl:verify-certificate false; set ftp:ssl-force true; set ftp:ssl-protect-data true;"
  else
    SSL_SETTINGS="set ssl:verify-certificate true; set ftp:ssl-force true; set ftp:ssl-protect-data true;"
  fi
else
  SSL_SETTINGS="set ftp:ssl-force false; set ftp:ssl-protect-data false;"
fi

echo "Deploy php-app -> ${FTP_HOST}:${FTP_REMOTE_DIR}"

lftp -u "${FTP_USER}","${FTP_PASS}" -p "${FTP_PORT}" "${FTP_HOST}" <<EOF
set net:max-retries 2
set net:timeout 20
set cmd:fail-exit true
${SSL_SETTINGS}
lcd "${APP_DIR}"
cd "${FTP_REMOTE_DIR}"
mirror -R --delete --verbose \
  --exclude-glob .git/ \
  --exclude-glob .github/ \
  --exclude-glob node_modules/ \
  --exclude-glob tests/ \
  --exclude-glob storage/logs/ \
  --exclude-glob storage/framework/cache/data/ \
  --exclude-glob storage/framework/sessions/ \
  --exclude-glob storage/framework/views/ \
  --exclude-glob .env \
  --exclude-glob .env.* \
  --exclude-glob docker-compose.yml \
  
bye
EOF

echo "Готово."
