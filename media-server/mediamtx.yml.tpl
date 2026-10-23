# Donums media server configuration (MediaMTX). Generated values are filled in by install.sh.
logLevel: info
logDestinations: [stdout]

# Every publish/read request is checked with the Donums website (signed, expiring tokens).
authMethod: http
authHTTPAddress: __AUTH_URL__
authHTTPExclude:
  - action: api
  - action: metrics
  - action: pprof
  - action: playback

api: no
metrics: no
pprof: no
playback: no
rtmp: no
hls: no
srt: no
moq: no

# RTSP is used only on this machine, by the restream helper (ffmpeg) to read the feed.
rtsp: yes
rtspAddress: 127.0.0.1:8554
rtspTransports: [tcp]

# WebRTC: the host publishes (WHIP) and viewers watch (WHEP). TLS is provided by the reverse proxy.
webrtc: yes
webrtcAddress: 127.0.0.1:8889
webrtcEncryption: no
webrtcAllowOrigins: [__SITE_ORIGINS__]
webrtcLocalUDPAddress: :8189
webrtcLocalTCPAddress: :8189
webrtcIPsFromInterfaces: yes
webrtcAdditionalHosts: [__PUBLIC_IP__]
webrtcICEServers2:
  - url: stun:stun.l.google.com:19302
webrtcHandshakeTimeout: 15s
writeQueueSize: 1024

paths:
  all_others:
