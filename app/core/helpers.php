<?php
function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function base_url(): string {
    return rtrim(config()['app']['base_url'] ?? '', '/');
}
function url(string $route = '', array $params = []): string {
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    return base_url() . '/index.php' . ($q ? '?' . http_build_query($q) : '');
}
function asset(string $path): string {
    return base_url() . '/assets/' . ltrim($path, '/');
}
function redirect(string $route, array $params = []): never {
    header('Location: ' . url($route, $params));
    exit;
}
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
function old(string $key, string $default = ''): string {
    return e((string)($_SESSION['old'][$key] ?? $default));
}
function sel(string $key, $value): string {
    return ((string)($_SESSION['old'][$key] ?? '') === (string)$value) ? ' selected' : '';
}
function view(string $name, array $data = []): void {
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/views/layout/header.php';
    require APP_ROOT . '/app/views/' . $name . '.php';
    require APP_ROOT . '/app/views/layout/footer.php';
    unset($_SESSION['old']);
}
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}
function audit(string $action, string $entity, ?int $entityId = null, ?string $details = null, ?int $userId = null): void {
    $userId = $userId ?? (current_user()['id'] ?? null);
    $st = db()->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)');
    $st->execute([$userId, $action, $entity, $entityId, $details, client_ip()]);
}
function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void {
    $st = db()->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)');
    $st->execute([$userId, $type, $title, $message, $link]);
}
function counties(): array {
    return db()->query('SELECT id, name FROM counties ORDER BY name')->fetchAll();
}
function institutions(): array {
    return db()->query('SELECT id, name FROM institutions ORDER BY name')->fetchAll();
}
function areas(): array {
    return ['ICT / Software Development', 'Accounting & Finance', 'Human Resource Management',
        'Public Administration', 'Engineering', 'Health Sciences', 'Education', 'Agriculture',
        'Law', 'Communication & Media', 'Statistics & Data', 'Procurement & Supply Chain', 'Other'];
}
function badge(string $status): string {
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function doc_types(): array {
    return [
        'insurance'             => 'Insurance cover',
        'recommendation_letter' => 'Recommendation letter from school',
        'transcript'            => 'Transcript',
        'cv'                    => 'Curriculum vitae (CV)',
    ];
}
function upload_rules(): array {
    return [
        'pdf'  => ['mime' => 'application/pdf',
                   'detect' => ['application/pdf']],
        'doc'  => ['mime' => 'application/msword',
                   'detect' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office']],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   'detect' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
    ];
}
function notify_coordinators(int $institutionId, string $type, string $title, string $message, ?string $link = null): void {
    $q = db()->prepare("SELECT id FROM users WHERE role = 'coordinator' AND institution_id = ? AND is_active = 1");
    $q->execute([$institutionId]);
    foreach ($q->fetchAll() as $c) {
        notify((int)$c['id'], $type, $title, $message, $link);
    }
}
function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

function ministries(): array {
    return db()->query('SELECT id, name FROM ministries ORDER BY name')->fetchAll();
}

function unread_notifications(int $uid): int {
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}
