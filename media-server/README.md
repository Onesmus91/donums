# Donums Media Server — setup guide

This small server is what lets Donums Live:

* serve **100+ viewers** in one session (the interactive room alone is for ~25), and
* **stream live to YouTube, Facebook, LinkedIn** and any platform that accepts RTMP.

Your website stays on your current hosting. This server runs separately, because live video needs a machine that your shared hosting cannot provide. Nothing here uses a third-party meeting service — it is open-source software (MediaMTX and ffmpeg) running on **your** server.

## How it works (plain English)

1. The host opens the Donums room in a browser. The host and up to 5 guests talk to each other directly, exactly as before.
2. When the host presses **Go live to attendees**, the host's browser mixes everyone on stage into one picture and sound and sends that single feed to this media server.
3. Attendees' browsers pull that one feed from the media server (so the host's internet is not used once per viewer). They still chat, ask questions, answer polls and raise their hands through the website. The host can invite anyone on stage.
4. When the host ticks destinations and presses **Start social stream**, the website tells this server to copy the same feed to YouTube/Facebook/etc.

## What to rent

Any VPS (DigitalOcean, Hetzner, Contabo, Linode, AWS Lightsail, Truehost, etc.) running **Ubuntu 24.04** (or 22.04 / Debian 12):

| Audience | Suggested size |
|---|---|
| up to 100 viewers + social stream | **4 vCPU, 8 GB RAM, 1 Gbps network port** (≈ US$20–40 / month) |
| up to 50 viewers | 2 vCPU, 4 GB RAM |

Bandwidth is the real limit: the feed is about **2.5 Mbps per viewer**, so 100 viewers ≈ **250 Mbps** out while live (≈ 1.1 GB per viewer-hour). Check your provider's traffic allowance. Choose a data-centre close to your viewers (Europe or the Middle East usually give the best latency to Kenya; if your provider has Johannesburg or Nairobi, use that).
The social stream uses about one CPU core.

## Before you install

1. Create a DNS **A record** for a sub-domain, e.g. `live.donums.co.ke` → the VPS's IPv4 address. Wait until it resolves.
2. In **Admin → Live → Streaming**: click **Generate secret** and copy it. (It lets your website and this server trust each other.)
3. Make sure your website runs on HTTPS.

## Install (about 5 minutes)

Upload the `media-server` folder to the VPS (for example with `scp -r media-server root@YOUR_VPS_IP:/root/`), then:

```bash
ssh root@YOUR_VPS_IP
cd /root/media-server
sudo bash install.sh
```

Answer the four questions (media domain, website address, shared secret, server IP). The installer sets up MediaMTX, the restream helper, an automatic-HTTPS proxy (Caddy), the firewall (ports 80, 443/tcp and **8189 udp+tcp** for video), and starts everything on boot.

Then, in **Admin → Live → Streaming**: enter `https://live.donums.co.ke` as the **Media server address** → *Save* → **Test connection**. It should say *Media server connected … ffmpeg ready*.

## Running a broadcast

1. Admin → Live → **Schedule a webinar** (or *Start instant session*) and set **Delivery mode = Broadcast**. Set seats to your target (e.g. 100–150).
2. Add your social destinations once under **Streaming** (stream URL + key from each platform; the page shows where to find them). On YouTube, enable live streaming on the channel at least 24 hours before your first event.
3. In the room: **Start webinar** → open the **Broadcast** tab → **Go live to attendees** → tick your destinations → **Start social stream**. Social platforms display video **10–30 seconds behind**; that is normal.
4. Use **End for all** to finish; the social stream stops automatically.

**Host checklist:** wired connection or strong Wi-Fi with at least **8 Mbps upload**; keep the room tab open **in front** (do not minimise it — browsers slow down hidden tabs); use Chrome or Edge on a laptop for the host.

## Troubleshooting

| Problem | What to check |
|---|---|
| “Test connection” fails | DNS record points at the VPS; `systemctl status caddy donums-restream mediamtx`; the secret matches on both sides. |
| Host cannot go live | Browser console shows a CORS or 401 error → the website address entered during install must exactly match the site (both `donums.co.ke` and `www.` are allowed automatically). |
| Viewers see “Connecting…” forever | Port **8189 UDP/TCP** blocked by the VPS provider's firewall. Open it in the provider's control panel as well. |
| Works on Wi-Fi but not on mobile data for some viewers | Add a TURN server (Admin → Settings → ICE servers). A TURN server (e.g. coturn) relays video for networks that block direct connections. |
| Social platform says “no data” | Check the stream key (they expire); confirm the platform has live streaming enabled for your account. |
| Logs | `journalctl -u mediamtx -f` · `journalctl -u donums-restream -f` |

## Security notes

* Only the website can authorise anyone to publish or watch: every connection must present a short-lived signed token that the media server verifies with your website. Stream keys are encrypted on the website and are never sent to browsers.
* The restream helper only accepts requests carrying your shared secret, and only listens on the VPS itself (the proxy exposes `/restream` over HTTPS).
* To rotate the secret: generate a new one in the admin, then run `sudo nano /etc/donums-media/helper.env`, change `HELPER_SECRET`, and `sudo systemctl restart donums-restream`.

## Status of this package

The components (MediaMTX configuration, restream helper, website integration, browser publishing and playback) were tested together end-to-end on a development machine with real browsers: publishing, 50 simultaneous viewers, and a restream verified to arrive as H.264 video + AAC audio at 1280×720. `install.sh` (systemd, Caddy, firewall) could not be run on a real VPS in that environment, so do your first installation on a Saturday, not an hour before an event, and run a private test broadcast first.
