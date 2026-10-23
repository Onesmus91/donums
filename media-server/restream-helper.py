#!/usr/bin/env python3
"""
Donums restream helper.

Runs on the same VPS as the media server (MediaMTX). The Donums website calls it
(over HTTPS through the reverse proxy, authenticated with the shared secret) to
start and stop pushing the live "program" feed to social-media RTMP endpoints.

  POST /start   {"path":"live/<key>","relay_password":"...","destinations":[{"name","url","key"}]}
  POST /stop    {"path":"live/<key>"}
  GET  /status[?path=live/<key>]

Standard library only. Needs: python3, ffmpeg (with libx264 + aac).
"""
import hmac, json, os, re, subprocess, sys, threading, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

SECRET = os.environ.get("HELPER_SECRET", "")
BIND = os.environ.get("HELPER_BIND", "127.0.0.1")
PORT = int(os.environ.get("HELPER_PORT", "8780"))
RTSP_BASE = os.environ.get("RTSP_BASE", "rtsp://127.0.0.1:8554")
FFMPEG = os.environ.get("FFMPEG", "ffmpeg")
VIDEO_KBPS = int(os.environ.get("VIDEO_KBPS", "2500"))
PATH_RE = re.compile(r"^live/[a-f0-9]{8,40}$")
BAD_CHARS = re.compile(r"[\s|\[\]'\"\\;`$<>]")

streams = {}          # path -> dict
lock = threading.Lock()


def log(*a):
    print(time.strftime("%H:%M:%S"), *a, flush=True)


def mask(s):
    return re.sub(r"(rtmps?://[^/]+/[^/\s]*/)[^\s|\]]+", r"\1****", s)


def build_cmd(path, relay_password, dests):
    src = f"{RTSP_BASE.replace('rtsp://', f'rtsp://relay:{relay_password}@')}/{path}"
    outs = [d["url"].rstrip("/") + "/" + d["key"] for d in dests]
    cmd = [FFMPEG, "-hide_banner", "-loglevel", "warning", "-nostats", "-progress", "pipe:1",
           "-use_wallclock_as_timestamps", "1", "-rtsp_transport", "tcp", "-i", src,
           "-map", "0:v:0", "-map", "0:a:0?",
           # constant 25 fps, fixed 1280x720 (letterboxed) whatever the browser sends: social platforms need a steady stream
           "-vf", "fps=25,scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,format=yuv420p",
           "-c:v", "libx264", "-preset", "veryfast", "-profile:v", "main", "-level:v", "3.1",
           "-b:v", f"{VIDEO_KBPS}k", "-maxrate", f"{int(VIDEO_KBPS*1.15)}k", "-bufsize", f"{VIDEO_KBPS*2}k",
           "-g", "50", "-keyint_min", "50", "-sc_threshold", "0",
           "-af", "aresample=async=1:first_pts=0", "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-ac", "2"]
    if len(outs) == 1:
        cmd += ["-f", "flv", outs[0]]
    else:
        cmd += ["-f", "tee", "|".join(f"[f=flv:onfail=ignore]{o}" for o in outs)]
    return cmd


def supervise(path):
    st = streams[path]
    backoff = 2
    while st["desired"]:
        st["state"] = "connecting"
        cmd = build_cmd(path, st["relay_password"], st["dests"])
        log("start", path, "->", mask(" ".join(cmd[-1:])))
        try:
            p = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, bufsize=1)
        except OSError as e:
            st["state"], st["last_error"] = "failed", f"cannot run ffmpeg: {e}"
            return
        st["proc"] = p
        t0 = time.time()

        def read_err():
            for line in p.stderr:
                line = mask(line.strip())
                if line:
                    st["last_error"] = line[:300]
        threading.Thread(target=read_err, daemon=True).start()
        for line in p.stdout:
            line = line.strip()
            if "=" in line:
                k, v = line.split("=", 1)
                if k == "bitrate":
                    st["bitrate"] = v
                elif k == "speed":
                    st["speed"] = v
                elif k == "out_time_us" and v.isdigit() and int(v) > 0:
                    if st["state"] != "live":
                        st["state"] = "live"; st["live_since"] = time.time(); backoff = 2
        rc = p.wait()
        if not st["desired"]:
            break
        st["restarts"] += 1
        up = time.time() - t0
        log("ffmpeg exited", rc, "after", int(up), "s")
        st["state"] = "reconnecting"
        if st["restarts"] > 60:
            st["state"] = "failed"; st["desired"] = False; break
        time.sleep(backoff); backoff = min(backoff * 2, 15)
    if st["state"] != "failed":
        st["state"] = "stopped"


def stop(path):
    with lock:
        st = streams.get(path)
        if not st:
            return False
        st["desired"] = False
        p = st.get("proc")
    if p and p.poll() is None:
        p.terminate()
        try:
            p.wait(timeout=5)
        except subprocess.TimeoutExpired:
            p.kill()
    return True


def public(path, st):
    return {"path": path, "state": st["state"], "restarts": st["restarts"], "bitrate": st.get("bitrate"), "speed": st.get("speed"),
            "uptime": int(time.time() - st["live_since"]) if st.get("live_since") and st["state"] == "live" else 0,
            "destinations": [d["name"] for d in st["dests"]], "last_error": st.get("last_error")}


class H(BaseHTTPRequestHandler):
    server_version = "DonumsRestream/1"

    def log_message(self, *a):
        pass

    def reply(self, code, obj):
        b = json.dumps(obj).encode()
        self.send_response(code); self.send_header("Content-Type", "application/json"); self.send_header("Content-Length", str(len(b))); self.end_headers(); self.wfile.write(b)

    def authed(self):
        tok = self.headers.get("Authorization", "")
        return SECRET and tok.startswith("Bearer ") and hmac.compare_digest(tok[7:].encode(), SECRET.encode())

    def body(self):
        n = int(self.headers.get("Content-Length") or 0)
        try:
            return json.loads(self.rfile.read(n) or b"{}")
        except ValueError:
            return {}

    def route(self):
        # the reverse proxy forwards /restream/* as-is; accept both forms
        u = urlparse(self.path); p = u.path
        return (p[len("/restream"):] if p.startswith("/restream") else p), parse_qs(u.query)

    def do_GET(self):
        if not self.authed():
            return self.reply(401, {"error": "unauthorised"})
        route, q = self.route()
        if route == "/status":
            try:
                ff = subprocess.run([FFMPEG, "-version"], capture_output=True, text=True, timeout=5).returncode == 0
            except Exception:
                ff = False
            try:
                import socket; socket.create_connection(("127.0.0.1", int(RTSP_BASE.rsplit(":", 1)[1])), 2).close(); mtx = "MediaMTX reachable"
            except Exception:
                mtx = "MediaMTX NOT reachable"
            only = q.get("path", [None])[0]
            with lock:
                items = [public(p, s) for p, s in streams.items() if (not only or p == only) and s["state"] != "stopped"]
            return self.reply(200, {"ok": True, "ffmpeg": ff, "mediamtx": mtx, "streams": items})
        self.reply(404, {"error": "not found"})

    def do_POST(self):
        if not self.authed():
            return self.reply(401, {"error": "unauthorised"})
        route, _ = self.route(); b = self.body()
        path = str(b.get("path", ""))
        if not PATH_RE.match(path):
            return self.reply(400, {"error": "invalid path"})
        if route == "/start":
            dests = []
            for d in b.get("destinations", [])[:6]:
                url, key = str(d.get("url", "")), str(d.get("key", ""))
                if not re.match(r"^rtmps?://[A-Za-z0-9.\-:/_]+$", url) or not key or BAD_CHARS.search(key) or BAD_CHARS.search(url):
                    return self.reply(400, {"error": f"destination '{d.get('name','?')}' has an invalid server address or stream key"})
                dests.append({"name": str(d.get("name", "destination"))[:60], "url": url, "key": key})
            if not dests or not re.match(r"^[A-Za-z0-9]{16,80}$", str(b.get("relay_password", ""))):
                return self.reply(400, {"error": "destinations and relay password are required"})
            stop(path); time.sleep(0.3)
            with lock:
                streams[path] = {"desired": True, "state": "connecting", "restarts": 0, "dests": dests, "relay_password": b["relay_password"], "started": time.time()}
            threading.Thread(target=supervise, args=(path,), daemon=True).start()
            return self.reply(200, {"ok": True, "started": [d["name"] for d in dests]})
        if route == "/stop":
            return self.reply(200, {"ok": True, "was_running": stop(path)})
        self.reply(404, {"error": "not found"})


if __name__ == "__main__":
    if len(SECRET) < 16:
        sys.exit("HELPER_SECRET must be set (at least 16 characters).")
    log(f"Donums restream helper on {BIND}:{PORT}")
    ThreadingHTTPServer((BIND, PORT), H).serve_forever()
