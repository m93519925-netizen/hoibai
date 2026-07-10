<?php
define('SUPABASE_URL',      getenv('SUPABASE_URL')      ?: 'https://herupcrorrzdaqivwjgl.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhlcnVwY3JvcnJ6ZGFxaXZ3amdsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODM1ODYwNzYsImV4cCI6MjA5OTE2MjA3Nn0.gCB-ZaEuFl57wlbwdxSOrcBSDhH-ztwTl8lZGU8UsSQ');

session_start();

function supabase_request(string $endpoint, string $method = 'GET', array $body = [], array $extra_headers = []): array {
    $url     = SUPABASE_URL . $endpoint;
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . ($_SESSION['access_token'] ?? SUPABASE_ANON_KEY),
        'Prefer: return=representation',
    ];
    $headers = array_merge($headers, $extra_headers);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if (!empty($body)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['data' => json_decode($response, true) ?? [], 'status' => $status];
}

function auth_sign_up(string $email, string $password): array {
    return supabase_request('/auth/v1/signup', 'POST', ['email' => $email, 'password' => $password]);
}
function auth_sign_in(string $email, string $password): array {
    return supabase_request('/auth/v1/token?grant_type=password', 'POST', ['email' => $email, 'password' => $password]);
}
function get_supabase_user(): ?array {
    if (empty($_SESSION['access_token'])) return null;
    $r = supabase_request('/auth/v1/user');
    return $r['status'] === 200 ? $r['data'] : null;
}
function db_select(string $table, string $query = ''): array {
    return supabase_request("/rest/v1/{$table}?{$query}");
}
function db_insert(string $table, array $data): array {
    return supabase_request("/rest/v1/{$table}", 'POST', $data);
}
function db_update(string $table, string $filter, array $data): array {
    return supabase_request("/rest/v1/{$table}?{$filter}", 'PATCH', $data);
}
function db_delete(string $table, string $filter): array {
    return supabase_request("/rest/v1/{$table}?{$filter}", 'DELETE');
}
function db_rpc(string $fn, array $params = []): array {
    return supabase_request("/rest/v1/rpc/{$fn}", 'POST', $params);
}

function storage_upload(string $tmp_path, string $original_name, string $folder = 'questions'): ?string {
    $ext     = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    $filename = $folder.'/'.time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
    $url      = SUPABASE_URL.'/storage/v1/object/qa-images/'.$filename;
    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.($_SESSION['access_token'] ?? SUPABASE_ANON_KEY),
            'apikey: '.SUPABASE_ANON_KEY,
            'Content-Type: image/'.($ext==='jpg'?'jpeg':$ext),
        ],
        CURLOPT_POSTFIELDS => file_get_contents($tmp_path),
        CURLOPT_TIMEOUT    => 30,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status === 200 ? SUPABASE_URL.'/storage/v1/object/public/qa-images/'.$filename : null;
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}
function nl2br_safe(string $str): string { return nl2br(h($str)); }

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_verify(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('CSRF token không hợp lệ.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function rate_limit(string $action, int $max, int $window = 60): void {
    $now = time(); $key = 'rl_'.$action;
    if (empty($_SESSION[$key]) || ($now - $_SESSION[$key]['start']) >= $window) {
        $_SESSION[$key] = ['count' => 0, 'start' => $now];
    }
    $_SESSION[$key]['count']++;
    if ($_SESSION[$key]['count'] > $max) {
        http_response_code(429);
        die('<p style="font-family:sans-serif;text-align:center;padding:40px">⏳ Quá nhiều yêu cầu. Vui lòng thử lại sau '.$window.' giây.</p>');
    }
}
function sanitize_search(string $input): string {
    return preg_replace('/[^\p{L}\p{N}\s.\-!?]/u', '', mb_substr(trim($input), 0, 100));
}
function require_login(): array {
    $user = get_supabase_user();
    if (!$user) { header('Location: /login.php'); exit; }
    return $user;
}
function get_profile(string $user_id): ?array {
    $r = db_select('profiles', "id=eq.{$user_id}&select=*");
    return $r['data'][0] ?? null;
}
function deduct_points(string $user_id, int $amount, string $reason, ?string $ref_id = null): bool {
    $p = get_profile($user_id);
    if (!$p || $p['points'] < $amount) return false;
    db_update('profiles', "id=eq.{$user_id}", ['points' => $p['points'] - $amount]);
    $v = get_profile($user_id);
    if (($v['points'] ?? 0) < 0) {
        db_update('profiles', "id=eq.{$user_id}", ['points' => $p['points']]);
        return false;
    }
    db_insert('point_transactions', ['user_id'=>$user_id,'amount'=>-$amount,'reason'=>$reason,'ref_id'=>$ref_id]);
    return true;
}
function add_points(string $user_id, int $amount, string $reason, ?string $ref_id = null): void {
    $p = get_profile($user_id);
    if (!$p) return;
    db_update('profiles', "id=eq.{$user_id}", ['points' => $p['points'] + $amount]);
    db_insert('point_transactions', ['user_id'=>$user_id,'amount'=>$amount,'reason'=>$reason,'ref_id'=>$ref_id]);
}
function time_ago(string $date): string {
    $diff = time() - strtotime($date);
    if ($diff < 60)    return 'vừa xong';
    if ($diff < 3600)  return floor($diff/60).' phút trước';
    if ($diff < 86400) return floor($diff/3600).' giờ trước';
    return floor($diff/86400).' ngày trước';
}
