<?php
require_once __DIR__ . '/../models/report.php';

function audit_index(): void {
    require_role('admin');
    $range = rpt_range();
    $action = (string)($_GET['action'] ?? '');
    $actions = db()->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($action, $actions, true)) $action = '';

    $where = '1=1';
    $p = [];
    if ($action !== '') { $where .= ' AND a.action = ?'; $p[] = $action; }
    $where .= rpt_range_sql($range, 'a.created_at', $p);

    $per = 50;
    $cnt = db()->prepare("SELECT COUNT(*) FROM audit_log a WHERE $where");
    $cnt->execute($p);
    $total = (int)$cnt->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    $off = ($page - 1) * $per;

    $st = db()->prepare("SELECT a.*, u.username, u.full_name, u.role FROM audit_log a
        LEFT JOIN users u ON u.id = a.user_id WHERE $where ORDER BY a.id DESC LIMIT $per OFFSET $off");
    $st->execute($p);
    view('audit/index', [
        'title' => 'Audit log', 'rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'action' => $action, 'actions' => $actions, 'range' => $range,
        'query' => array_filter(['action' => $action, 'from' => $range['from'], 'to' => $range['to']]),
    ]);
}
