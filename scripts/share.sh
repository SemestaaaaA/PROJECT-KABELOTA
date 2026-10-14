#!/usr/bin/env bash
# Buka tunnel Cloudflare ke server demo (:8000) dan arahkan APP_URL ke URL tunnel,
# supaya link di email (verifikasi, reset password) bisa dibuka dari luar.
# APP_URL lama dikembalikan otomatis saat tunnel ditutup (Ctrl+C).
#
#   bash scripts/share.sh        → mode biasa
#   bash scripts/share.sh alt    → paksa http2 + IPv4 (jaringan yang memblokir QUIC)
set -u
cd "$(dirname "$0")/.."

ENV_FILE=.env
ORIGINAL_URL=$(grep -E '^APP_URL=' "$ENV_FILE" | head -1 | cut -d= -f2-)
# Sisa tunnel lama yang tidak sempat dikembalikan → pulihkan ke localhost.
case "$ORIGINAL_URL" in *trycloudflare.com*) ORIGINAL_URL=http://localhost:8000 ;; esac

set_app_url() {
    sed -i '' -E "s#^APP_URL=.*#APP_URL=$1#" "$ENV_FILE"
    php artisan config:clear >/dev/null 2>&1
    # Worker queue menyimpan APP_URL lama di memori; minta ia berhenti dengan rapi,
    # loop di `composer local` langsung menyalakannya lagi dengan APP_URL baru.
    php artisan queue:restart >/dev/null 2>&1
}

restore() {
    set_app_url "$ORIGINAL_URL"
    echo
    echo "APP_URL dikembalikan ke $ORIGINAL_URL"
}
trap restore EXIT

ARGS=(tunnel --url http://127.0.0.1:8000)
if [ "${1:-}" = "alt" ]; then
    ARGS=(tunnel --protocol http2 --edge-ip-version 4 --edge 198.41.192.77:7844 --edge 198.41.200.33:7844 --url http://127.0.0.1:8000)
fi

FOUND=""
cloudflared "${ARGS[@]}" 2>&1 | while IFS= read -r line; do
    echo "$line"
    if [ -z "$FOUND" ]; then
        url=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' <<<"$line")
        if [ -n "$url" ]; then
            FOUND=1
            set_app_url "$url"
            echo
            echo "=============================================================="
            echo "  Link demo : $url"
            echo "  APP_URL   : sudah diarahkan ke link ini (email ikut benar)"
            echo "  Tunggu ±20 detik sampai muncul 'Registered tunnel connection'"
            echo "=============================================================="
            echo
        fi
    fi
done
