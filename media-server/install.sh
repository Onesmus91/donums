#!/usr/bin/env bash
# Donums media server installer — Ubuntu 22.04 / 24.04 or Debian 12, run as root on a fresh VPS.
#   sudo bash install.sh
# You will be asked for: the media domain (e.g. live.donums.co.ke), your website address, and the shared secret
# generated in Admin > Live > Streaming. Point the domain's DNS "A" record at this server BEFORE running.
set -euo pipefail
MTX_VERSION="v1.21.2"
[ "$(id -u)" = 0 ] || { echo "Run as root (sudo)."; exit 1; }
HERE="$(cd "$(dirname "$0")" && pwd)"

read -rp "Media domain (DNS A record must already point here), e.g. live.donums.co.ke: " DOMAIN
read -rp "Your website address, e.g. https://donums.co.ke: " SITE
read -rp "Shared secret from Admin > Live > Streaming: " SECRET
[ ${#SECRET} -ge 16 ] || { echo "The secret must be at least 16 characters."; exit 1; }
SITE="${SITE%/}"
HOSTPART="${SITE#*://}"; SCHEME="${SITE%%://*}"
if [[ "$HOSTPART" == www.* ]]; then ALT="${SCHEME}://${HOSTPART#www.}"; else ALT="${SCHEME}://www.${HOSTPART}"; fi
ORIGINS="\"${SITE}\", \"${ALT}\""
PUBLIC_IP="$(curl -4 -fsS https://api.ipify.org || true)"
read -rp "This server's public IPv4 [${PUBLIC_IP}]: " IN; PUBLIC_IP="${IN:-$PUBLIC_IP}"

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ffmpeg python3 curl ca-certificates debian-keyring debian-archive-keyring apt-transport-https gnupg ufw >/dev/null
if ! command -v caddy >/dev/null; then
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
  apt-get update -qq && apt-get install -y -qq caddy >/dev/null
fi

echo "==> Installing MediaMTX ${MTX_VERSION}"
id donums-media >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin donums-media
mkdir -p /opt/donums-media /etc/donums-media
curl -fsSL "https://github.com/bluenviron/mediamtx/releases/download/${MTX_VERSION}/mediamtx_${MTX_VERSION}_linux_amd64.tar.gz" | tar -xz -C /opt/donums-media mediamtx
cp "$HERE/restream-helper.py" /opt/donums-media/restream-helper.py
chmod 755 /opt/donums-media/mediamtx /opt/donums-media/restream-helper.py

echo "==> Writing configuration"
sed -e "s#__AUTH_URL__#${SITE}/api/media/auth#" -e "s#__SITE_ORIGINS__#${ORIGINS}#" -e "s#__PUBLIC_IP__#${PUBLIC_IP}#" "$HERE/mediamtx.yml.tpl" > /etc/donums-media/mediamtx.yml
printf 'HELPER_SECRET=%s\nHELPER_PORT=8780\nVIDEO_KBPS=2500\n' "$SECRET" > /etc/donums-media/helper.env
chmod 640 /etc/donums-media/helper.env; chown root:donums-media /etc/donums-media/helper.env
sed -e "s#__DOMAIN__#${DOMAIN}#" "$HERE/Caddyfile.tpl" > /etc/caddy/Caddyfile
cp "$HERE/mediamtx.service" "$HERE/donums-restream.service" /etc/systemd/system/

echo "==> Firewall"
ufw allow OpenSSH >/dev/null; ufw allow 80,443/tcp >/dev/null; ufw allow 8189/udp >/dev/null; ufw allow 8189/tcp >/dev/null; ufw --force enable >/dev/null

echo "==> Starting services"
systemctl daemon-reload
systemctl enable --now mediamtx donums-restream >/dev/null
systemctl restart caddy
sleep 3
systemctl is-active mediamtx donums-restream caddy
cat <<DONE

Done. Next:
  1. In the Donums admin: Live > Streaming > Media server address = https://${DOMAIN}  -> Save -> "Test connection".
  2. Create a webinar with Delivery mode = Broadcast.
Logs:  journalctl -u mediamtx -f     journalctl -u donums-restream -f
DONE
