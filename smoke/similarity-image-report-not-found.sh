#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://localhost}"
NON_EXISTING_IMAGE_ID="${NON_EXISTING_IMAGE_ID:-999999999}"

URL="${BASE_URL%/}/notary/archiver-dashboard/similarity-image-report?image_id=${NON_EXISTING_IMAGE_ID}"
HTTP_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "$URL")"

if [[ "$HTTP_CODE" != "404" ]]; then
  echo "Smoke check failed: expected HTTP 404, got ${HTTP_CODE} for ${URL}" >&2
  exit 1
fi

echo "Smoke check passed: got HTTP 404 for ${URL}"
