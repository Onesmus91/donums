<?php
/* =====================================================================
 * MIME parser: raw RFC 822 message -> headers, text, html, attachments.
 * ===================================================================== */

function mime_decode_words(string $s): string {
    if (!str_contains($s, '=?')) { return $s; }
    $s = preg_replace('/\?=\s+=\?/', '?==?', $s);
    return preg_replace_callback('/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/', function ($m) {
        $t = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
        return mime_to_utf8($t, $m[1]);
    }, $s);
}
function mime_to_utf8(string $t, string $charset): string {
    $cs = strtoupper(trim($charset));
    if ($cs === '' || $cs === 'UTF-8' || $cs === 'US-ASCII') { return mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1'); }
    $r = @mb_convert_encoding($t, 'UTF-8', $cs);
    return $r !== false ? $r : $t;
}
function mime_split(string $raw): array {
    $raw = str_replace("\r\n", "\n", $raw);
    $p = strpos($raw, "\n\n");
    if ($p === false) { return [$raw, '']; }
    return [substr($raw, 0, $p), substr($raw, $p + 2)];
}
function mime_headers(string $block): array {
    $h = []; $block = preg_replace("/\n[ \t]+/", ' ', $block);
    foreach (explode("\n", $block) as $line) {
        if (($i = strpos($line, ':')) === false) { continue; }
        $k = strtolower(trim(substr($line, 0, $i)));
        $h[$k][] = trim(substr($line, $i + 1));
    }
    return $h;
}
function mime_param(string $header, string $name): string {
    if (preg_match('/' . $name . '\*=([^\']*)\'[^\']*\'([^;]+)/i', $header, $m)) { return mime_to_utf8(rawurldecode(trim($m[2], '" ')), $m[1]); }
    if (preg_match('/' . $name . '="([^"]*)"/i', $header, $m)) { return mime_decode_words($m[1]); }
    if (preg_match('/' . $name . '=([^;\s]+)/i', $header, $m)) { return mime_decode_words($m[1]); }
    return '';
}
function mime_body_decode(string $body, string $enc): string {
    $enc = strtolower(trim($enc));
    if ($enc === 'base64') { return (string) base64_decode(preg_replace('/\s+/', '', $body)); }
    if ($enc === 'quoted-printable') { return quoted_printable_decode($body); }
    return $body;
}
function mime_addr_list(string $v): array {
    $out = [];
    foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', mime_decode_words($v)) as $p) {
        $p = trim($p); if ($p === '') { continue; }
        if (preg_match('/^(.*?)<([^>]+)>/', $p, $m)) { $out[] = [trim($m[2]), trim($m[1], " \"'")]; }
        elseif (mail_valid($p)) { $out[] = [$p, '']; }
    }
    return $out;
}

/** Parse a raw message. Attachment data is kept in memory ('data'). */
function mime_parse(string $raw): array {
    [$hb, $body] = mime_split($raw);
    $h = mime_headers($hb);
    $res = ['headers' => $h, 'text' => '', 'html' => '', 'attachments' => []];
    $res['subject'] = mime_decode_words($h['subject'][0] ?? '');
    $res['from'] = mime_addr_list($h['from'][0] ?? '')[0] ?? ['', ''];
    $res['to'] = mime_addr_list(implode(',', $h['to'] ?? []));
    $res['cc'] = mime_addr_list(implode(',', $h['cc'] ?? []));
    $res['message_id'] = trim($h['message-id'][0] ?? '');
    $res['in_reply_to'] = trim($h['in-reply-to'][0] ?? '');
    $res['date'] = isset($h['date'][0]) && ($t = strtotime($h['date'][0])) ? date('Y-m-d H:i:s', $t) : now();
    mime_walk($hb, $body, $res, 0);
    return $res;
}
function mime_walk(string $hb, string $body, array &$res, int $depth): void {
    if ($depth > 8) { return; }
    $h = mime_headers($hb);
    $ct = $h['content-type'][0] ?? 'text/plain';
    $type = strtolower(trim(explode(';', $ct)[0]));
    $enc = $h['content-transfer-encoding'][0] ?? '7bit';
    $disp = strtolower($h['content-disposition'][0] ?? '');
    if (str_starts_with($type, 'multipart/')) {
        $b = mime_param($ct, 'boundary');
        if ($b === '') { return; }
        foreach (explode('--' . $b, $body) as $i => $part) {
            if ($i === 0 || str_starts_with(ltrim($part, "\n"), '--')) { continue; }
            $part = ltrim($part, "\n");
            [$ph, $pb] = mime_split($part);
            mime_walk($ph, $pb, $res, $depth + 1);
        }
        return;
    }
    $filename = mime_param($h['content-disposition'][0] ?? '', 'filename') ?: mime_param($ct, 'name');
    $isAtt = str_contains($disp, 'attachment') || ($filename !== '' && !in_array($type, ['text/plain', 'text/html'], true)) || ($filename !== '' && str_contains($disp, 'inline') === false && $type !== 'text/plain' && $type !== 'text/html');
    $data = mime_body_decode($body, $enc);
    if (!$isAtt && in_array($type, ['text/plain', 'text/html'], true)) {
        $txt = mime_to_utf8($data, mime_param($ct, 'charset'));
        if ($type === 'text/html') { $res['html'] .= $txt; } else { $res['text'] .= $txt; }
        return;
    }
    if ($type === 'message/rfc822' && !$isAtt) { return; }
    $res['attachments'][] = [
        'filename' => $filename ?: ('attachment-' . (count($res['attachments']) + 1)), 'mime' => $type, 'size' => strlen($data),
        'content_id' => trim($h['content-id'][0] ?? '', '<> '), 'data' => $data,
    ];
}
