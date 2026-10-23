<?php
/* =====================================================================
 * Minimal pure-PHP IMAP client (no ext-imap needed — PHP 8.4 removed it).
 * Supports: LOGIN, SELECT, UID SEARCH, UID FETCH (full message), UID STORE.
 * ===================================================================== */

class ImapClient {
    private $fp; private int $tag = 0; public string $error = '';

    public function connect(string $host, int $port, string $security, string $user, string $pass): bool {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => !cfg('imap_insecure', false), 'verify_peer_name' => !cfg('imap_insecure', false), 'peer_name' => $host]]);
        $ssl = in_array(strtolower($security), ['ssl', 'ssl/tls', 'tls'], true);
        $this->fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) { $this->error = "Could not connect to $host:$port — $es"; return false; }
        stream_set_timeout($this->fp, 25);
        $greet = fgets($this->fp, 2048);
        if (!$greet || !str_contains($greet, 'OK')) { $this->error = 'Unexpected IMAP greeting.'; return false; }
        if (strtolower($security) === 'starttls') {
            [$ok] = $this->cmd('STARTTLS');
            if (!$ok || !@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $this->error = 'STARTTLS failed.'; return false; }
        }
        [$ok, $resp] = $this->cmd('LOGIN ' . $this->quote($user) . ' ' . $this->quote($pass));
        if (!$ok) { $this->error = 'The mail server rejected the username or password.'; return false; }
        return true;
    }
    private function quote(string $s): string { return '"' . addcslashes($s, "\\\"") . '"'; }

    /** @return array{0:bool,1:string} */
    public function cmd(string $c): array {
        $tag = 'A' . (++$this->tag);
        fwrite($this->fp, "$tag $c\r\n");
        $buf = '';
        while (($line = fgets($this->fp)) !== false) {
            $buf .= $line;
            // literal {n}: read n raw bytes
            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $n = (int) $m[1]; $got = '';
                while (strlen($got) < $n && !feof($this->fp)) { $got .= fread($this->fp, $n - strlen($got)); }
                $buf .= $got;
                continue;
            }
            if (str_starts_with($line, $tag . ' ')) { return [str_starts_with($line, "$tag OK"), $buf]; }
        }
        return [false, $buf];
    }
    public function select(string $box = 'INBOX'): int {
        [$ok, $r] = $this->cmd('SELECT ' . $box);
        return $ok && preg_match('/\* (\d+) EXISTS/', $r, $m) ? (int) $m[1] : -1;
    }
    /** UIDs greater than $after. */
    public function uidsAfter(int $after): array {
        [$ok, $r] = $this->cmd('UID SEARCH UID ' . ($after + 1) . ':*');
        if (!$ok || !preg_match('/\* SEARCH([ \d]*)/', $r, $m)) { return []; }
        $u = array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1]))), fn($x) => $x > $after));
        sort($u);
        return $u;
    }
    public function fetchRaw(int $uid): string {
        [$ok, $r] = $this->cmd("UID FETCH $uid (BODY.PEEK[])");
        if (!$ok) { return ''; }
        $p = strpos($r, "}\r\n"); if ($p === false) { return ''; }
        preg_match('/\{(\d+)\}\r\n/', $r, $m);
        return substr($r, $p + 3, (int) $m[1]);
    }
    public function fetchSize(int $uid): int {
        [$ok, $r] = $this->cmd("UID FETCH $uid (RFC822.SIZE)");
        return $ok && preg_match('/RFC822\.SIZE (\d+)/', $r, $m) ? (int) $m[1] : 0;
    }
    public function setFlag(int $uid, string $flag, bool $on): bool { return $this->cmd("UID STORE $uid " . ($on ? '+' : '-') . "FLAGS.SILENT ($flag)")[0]; }
    public function close(): void { if ($this->fp) { $this->cmd('LOGOUT'); @fclose($this->fp); $this->fp = null; } }
}

function imap_test(string $address): array {
    $a = mailbox_get($address);
    if (!$a || empty($a['password'])) { return [false, 'Save the mailbox password first.']; }
    $c = new ImapClient();
    if (!$c->connect($a['imap_host'], (int) $a['imap_port'], $a['imap_security'], $a['username'], $a['password'])) { return [false, $c->error]; }
    $n = $c->select('INBOX'); $c->close();
    return [true, 'IMAP connected successfully · ' . max(0, $n) . ' message(s) in the inbox.'];
}

/** Pull new inbox messages for one mailbox into the local store. Returns [added, error|null]. */
function mail_sync(string $address, int $max = 40): array {
    $a = mailbox_get($address);
    if (!$a || !$a['active'] || empty($a['password'])) { return [0, null]; }
    $c = new ImapClient();
    if (!$c->connect($a['imap_host'], (int) $a['imap_port'], $a['imap_security'], $a['username'], $a['password'])) {
        q('UPDATE mailboxes SET last_error = ?, last_sync_at = ? WHERE address = ?', [$c->error, now(), $address]);
        return [0, $c->error];
    }
    $c->select('INBOX');
    $last = (int) $a['last_uid'];
    if ($last === 0) { // first sync: only the most recent messages, not years of history
        $all = $c->uidsAfter(0); $uids = array_slice($all, -$max);
    } else { $uids = array_slice($c->uidsAfter($last), 0, $max); }
    $added = 0; $top = $last;
    foreach ($uids as $uid) {
        $top = max($top, $uid);
        if (val('SELECT 1 FROM mail_messages WHERE mailbox = ? AND uid = ?', [$address, $uid])) { continue; }
        $dupe = false;
        if ($c->fetchSize($uid) > 25 * 1024 * 1024) { continue; }
        $raw = $c->fetchRaw($uid);
        if ($raw === '') { continue; }
        $parsed = mime_parse($raw);
        // Messages imported from the previous system have no UID: avoid duplicating them.
        if (val("SELECT 1 FROM mail_messages WHERE mailbox = ? AND uid IS NULL AND folder = 'inbox' AND subject = ? AND from_email = ? AND substr(msg_date,1,16) = substr(?,1,16)", [$address, $parsed['subject'] ?: '(No subject)', strtolower($parsed['from'][0]), $parsed['date']])) { continue; }
        store_incoming($address, $uid, $parsed);
        $added++;
    }
    $c->close();
    q('UPDATE mailboxes SET last_uid = ?, last_sync_at = ?, last_error = NULL WHERE address = ?', [$top, now(), $address]);
    return [$added, null];
}

function store_incoming(string $address, int $uid, array $p): int {
    $text = trim($p['text']) !== '' ? $p['text'] : html_to_text($p['html']);
    $id = insert('mail_messages', [
        'mailbox' => $address, 'folder' => 'inbox', 'uid' => $uid, 'message_id' => $p['message_id'], 'in_reply_to' => $p['in_reply_to'],
        'thread_key' => thread_key($p['subject']), 'from_name' => $p['from'][1], 'from_email' => strtolower($p['from'][0]),
        'to_json' => json_encode($p['to']), 'cc_json' => json_encode($p['cc']), 'subject' => $p['subject'] ?: '(No subject)',
        'snippet' => excerpt($text, 140), 'body_text' => $text, 'body_html' => $p['html'], 'has_att' => $p['attachments'] ? 1 : 0,
        'is_read' => 0, 'status' => 'Received', 'msg_date' => $p['date'], 'created_at' => now(),
    ]);
    foreach ($p['attachments'] as $a) {
        $dir = 'mail/' . date('Y-m'); @mkdir(DATA_DIR . '/private/' . $dir, 0750, true);
        $name = bin2hex(random_bytes(10)) . '.bin';
        file_put_contents(DATA_DIR . '/private/' . $dir . '/' . $name, $a['data']);
        insert('mail_attachments', ['message_id' => $id, 'filename' => mb_substr($a['filename'], 0, 160), 'mime' => $a['mime'], 'size' => $a['size'], 'path' => $dir . '/' . $name, 'content_id' => $a['content_id']]);
    }
    // Remember who wrote to us
    if (mail_valid($p['from'][0])) { q('INSERT OR IGNORE INTO contacts(email,name,source,subscribed,created_at) VALUES(?,?,?,0,?)', [strtolower($p['from'][0]), $p['from'][1], 'Inbound mail', now()]); }
    return $id;
}
