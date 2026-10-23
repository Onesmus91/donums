# TLS reverse proxy (automatic HTTPS certificates). Replace the domain; install.sh does this for you.
__DOMAIN__ {
	encode gzip
	handle /restream/* {
		reverse_proxy 127.0.0.1:8780
	}
	handle {
		reverse_proxy 127.0.0.1:8889
	}
}
