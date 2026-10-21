<?php
require_once __DIR__ . '/vacancy.php';
require_once __DIR__ . '/student.php';
// Helper names avoid route prefixes (application_, vacancy_, notification_...) so they can never be reached as routes.

// Match score 0-100: area 50 + county 30 + period 20 (equal) or 10 (student available longer).
function match_breakdown(array $s, array $v): array {
    $area   = ($s['area_of_interest'] === $v['course_area']) ? 50 : 0;
    $county = ((int)$s['preferred_county_id'] === (int)$v['county_id']) ? 30 : 0;
    $sp = (int)$s['period_months'];
    $vd = (int)$v['duration_months'];
    $period = $sp === $vd ? 20 : ($sp > $vd ? 10 : 0);
    return ['area' => $area, 'county' => $county, 'period' => $period, 'total' => $area + $county + $period];
}
function match_score(array $s, array $v): int {
    return match_breakdown($s, $v)['total'];
}
// Automatic placement: the area of interest must match AND the total must reach this minimum.
function auto_min_match(): int {
    $v = (int)(config()['placement']['auto_min_match'] ?? 50);
    return max(1, min(100, $v));
}
function auto_placement_ok(array $m): bool {
    return $m['area'] > 0 && $m['total'] >= auto_min_match();
}
function block_reason_for_vacancy(array $v): ?string {
    $today = date('Y-m-d');
    if ($v['status'] !== 'open') return 'This vacancy is not open for applications.';
    if (open_slots($v) < 1) return 'All slots for this vacancy are filled.';
    if ($v['closes_on'] < $today) return 'This vacancy closed on ' . $v['closes_on'] . '.';
    if ($v['opens_on'] > $today) return 'This vacancy opens on ' . $v['opens_on'] . '.';
    return null;
}
// Single source of truth for "can this user apply?" (used by the page and by the apply action).
function apply_state(array $v, ?array $u): array {
    if (!$u) return ['state' => 'login'];
    if ($u['role'] !== 'student') return ['state' => 'none'];
    $s = get_student_by_user((int)$u['id']);
    if (!$s) return ['state' => 'none'];
    $q = db()->prepare('SELECT status FROM applications WHERE student_id = ? AND vacancy_id = ?');
    $q->execute([$s['id'], $v['id']]);
    $status = $q->fetchColumn();
    if ($status !== false && $status !== 'withdrawn') {
        return ['state' => 'applied', 'msg' => 'You already applied to this vacancy (status: ' . $status . ').'];
    }
    $q = db()->prepare('SELECT COUNT(*) FROM placements WHERE student_id = ?');
    $q->execute([$s['id']]);
    if ((int)$q->fetchColumn() > 0) {
        return ['state' => 'placed', 'msg' => 'You already have a placement (it may be awaiting Ministry approval), so you cannot apply for more vacancies.'];
    }
    if ($s['verification_status'] !== 'verified') {
        return ['state' => 'not_verified', 'msg' => 'Only verified students can apply. Your status is ' . $s['verification_status'] . '.'];
    }
    $reason = block_reason_for_vacancy($v);
    if ($reason) return ['state' => 'closed', 'msg' => $reason];
    if (!active_window()) return ['state' => 'no_window', 'msg' => 'No application window is open right now.'];
    return ['state' => 'can_apply', 'student' => $s, 'match' => match_breakdown($s, $v)];
}
// Open vacancies in the student's area of interest, best match first (excludes ones already applied to).
function suggested_vacancies(array $s, int $limit = 5): array {
    $st = db()->prepare("SELECT v.*, m.name AS ministry, c.name AS county FROM vacancies v
        JOIN ministries m ON m.id = v.ministry_id JOIN counties c ON c.id = v.county_id
        WHERE v.status = 'open' AND v.slots_filled < v.slots_total AND v.closes_on >= CURDATE() AND v.opens_on <= CURDATE()
        AND v.course_area = ? AND v.id NOT IN (SELECT vacancy_id FROM applications WHERE student_id = ?)");
    $st->execute([$s['area_of_interest'], $s['id']]);
    $rows = [];
    foreach ($st->fetchAll() as $v) {
        $v['match'] = match_breakdown($s, $v);
        $rows[] = $v;
    }
    usort($rows, fn($a, $b) => [$b['match']['total'], $a['closes_on'], $a['id']] <=> [$a['match']['total'], $b['closes_on'], $b['id']]);
    return array_slice($rows, 0, $limit);
}
function next_matches_text(array $s, int $limit = 3): string {
    $list = suggested_vacancies($s, $limit);
    if (!$list) return 'No other matching vacancies are open right now; check back soon.';
    $parts = array_map(fn($v) => '"' . $v['title'] . '" (' . $v['ministry'] . ', ' . $v['county'] . ')', $list);
    return 'Other open vacancies that match you: ' . implode('; ', $parts) . '.';
}
function get_placement_for_student(int $studentId): ?array {
    $st = db()->prepare('SELECT p.*, v.title, v.department, v.office_location, m.name AS ministry, c.name AS county
        FROM placements p JOIN vacancies v ON v.id = p.vacancy_id JOIN ministries m ON m.id = v.ministry_id
        JOIN counties c ON c.id = v.county_id WHERE p.student_id = ?');
    $st->execute([$studentId]);
    return $st->fetch() ?: null;
}
function placement_end_date(?string $start, int $months): ?string {
    return $start ? (new DateTime($start))->modify('+' . $months . ' months')->format('Y-m-d') : null;
}
// When a vacancy is completely full AND nothing is waiting for approval, the remaining applicants
// are marked unsuccessful. Must be called inside a transaction. Returns the affected student ids.
function settle_vacancy(PDO $pdo, int $vacancyId, ?int $byUserId): array {
    $q = $pdo->prepare('SELECT slots_total, slots_filled FROM vacancies WHERE id = ?');
    $q->execute([$vacancyId]);
    $v = $q->fetch();
    if (!$v || (int)$v['slots_filled'] < (int)$v['slots_total']) return [];
    $q = $pdo->prepare("SELECT COUNT(*) FROM placements WHERE vacancy_id = ? AND status = 'pending'");
    $q->execute([$vacancyId]);
    if ((int)$q->fetchColumn() > 0) return [];
    $q = $pdo->prepare("SELECT student_id FROM applications WHERE vacancy_id = ? AND status IN ('applied','shortlisted')");
    $q->execute([$vacancyId]);
    $ids = array_map('intval', array_column($q->fetchAll(), 'student_id'));
    if ($ids) {
        $pdo->prepare("UPDATE applications SET status = 'unsuccessful', decision_note = 'All slots for this vacancy have been filled', decided_by = ?, decided_at = NOW() WHERE vacancy_id = ? AND status IN ('applied','shortlisted')")
            ->execute([$byUserId, $vacancyId]);
    }
    return $ids;
}
// Reserves a slot for an application, under row locks so slots can never be over-filled.
//   $d === null  -> automatic placement: a PENDING placement that the Ministry must approve.
//   $d = [...]   -> manual placement by the Ministry: confirmed straight away with those details.
function reserve_placement(int $appId, ?int $byUserId, ?array $d = null): array {
    $pdo = db();
    $fail = function (string $m) use ($pdo): array {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $m];
    };
    $losers = [];
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT * FROM applications WHERE id = ? FOR UPDATE');
        $q->execute([$appId]);
        $app = $q->fetch();
        if (!$app) return $fail('Application not found.');
        $q = $pdo->prepare('SELECT * FROM vacancies WHERE id = ? FOR UPDATE');
        $q->execute([$app['vacancy_id']]);
        $vac = $q->fetch();
        if (!in_array($app['status'], ['applied', 'shortlisted'], true)) return $fail('This application is no longer active (status: ' . $app['status'] . ').');
        if ($d === null && $vac['status'] !== 'open') return $fail('This vacancy is not open.');
        if ((int)$vac['slots_filled'] >= (int)$vac['slots_total']) return $fail('All slots for this vacancy are already filled.');
        $q = $pdo->prepare('SELECT verification_status FROM students WHERE id = ? FOR UPDATE');
        $q->execute([$app['student_id']]);
        if ($q->fetchColumn() !== 'verified') return $fail('This student is not verified.');
        $q = $pdo->prepare('SELECT COUNT(*) FROM placements WHERE student_id = ?');
        $q->execute([$app['student_id']]);
        if ((int)$q->fetchColumn() > 0) return $fail('This student already has a placement.');

        if ($d === null) {
            $pdo->prepare("INSERT INTO placements (application_id, student_id, vacancy_id, status) VALUES (?,?,?,'pending')")
                ->execute([$appId, $app['student_id'], $vac['id']]);
            $note = 'Placed automatically (first come, first served)';
        } else {
            $start = $d['start_date'] !== '' ? $d['start_date'] : null;
            $pdo->prepare("INSERT INTO placements (application_id, student_id, vacancy_id, start_date, end_date, supervisor_name, supervisor_phone, supervisor_email, status, confirmed_by) VALUES (?,?,?,?,?,?,?,?,'confirmed',?)")
                ->execute([$appId, $app['student_id'], $vac['id'], $start, placement_end_date($start, (int)$vac['duration_months']),
                    $d['supervisor_name'] !== '' ? $d['supervisor_name'] : null,
                    $d['supervisor_phone'] !== '' ? $d['supervisor_phone'] : null,
                    $d['supervisor_email'] !== '' ? $d['supervisor_email'] : null, $byUserId]);
            $note = 'Placement confirmed';
        }
        $pid = (int)$pdo->lastInsertId();
        $filled = (int)$vac['slots_filled'] + 1;
        $isFull = $filled >= (int)$vac['slots_total'];
        $pdo->prepare('UPDATE vacancies SET slots_filled = ?, status = ? WHERE id = ?')
            ->execute([$filled, $isFull ? 'filled' : $vac['status'], $vac['id']]);
        $pdo->prepare("UPDATE applications SET status = 'placed', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")
            ->execute([$byUserId, $note, $appId]);
        $pdo->prepare("UPDATE applications SET status = 'withdrawn', decision_note = 'Placed at another vacancy', decided_at = NOW() WHERE student_id = ? AND id <> ? AND status IN ('applied','shortlisted')")
            ->execute([$app['student_id'], $appId]);
        if ($d !== null) $losers = settle_vacancy($pdo, (int)$vac['id'], $byUserId);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23000') return ['ok' => false, 'msg' => 'This student already has a placement.'];
        throw $e;
    }
    return ['ok' => true, 'pending' => $d === null, 'placement_id' => $pid, 'student_id' => (int)$app['student_id'],
            'vacancy_id' => (int)$vac['id'], 'is_full' => $isFull, 'losers' => $losers];
}
// Manual placement by the Ministry (kept under its old name; also used by the demo seed).
function confirm_placement(int $appId, int $byUserId, array $d): array {
    return reserve_placement($appId, $byUserId, $d);
}
// The Ministry approves a pending (automatic) placement.
function approve_placement(int $placementId, int $byUserId, array $d): array {
    $pdo = db();
    $fail = function (string $m) use ($pdo): array {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $m];
    };
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT * FROM placements WHERE id = ? FOR UPDATE');
        $q->execute([$placementId]);
        $pl = $q->fetch();
        if (!$pl) return $fail('Placement not found.');
        if ($pl['status'] !== 'pending') return $fail('This placement is not waiting for approval.');
        $q = $pdo->prepare('SELECT * FROM vacancies WHERE id = ? FOR UPDATE');
        $q->execute([$pl['vacancy_id']]);
        $vac = $q->fetch();
        $start = $d['start_date'] !== '' ? $d['start_date'] : null;
        $pdo->prepare("UPDATE placements SET status = 'confirmed', start_date = ?, end_date = ?, supervisor_name = ?, supervisor_phone = ?, supervisor_email = ?, confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")
            ->execute([$start, placement_end_date($start, (int)$vac['duration_months']),
                $d['supervisor_name'] !== '' ? $d['supervisor_name'] : null,
                $d['supervisor_phone'] !== '' ? $d['supervisor_phone'] : null,
                $d['supervisor_email'] !== '' ? $d['supervisor_email'] : null, $byUserId, $placementId]);
        $pdo->prepare("UPDATE applications SET decided_by = ?, decided_at = NOW(), decision_note = 'Placement confirmed' WHERE id = ?")
            ->execute([$byUserId, $pl['application_id']]);
        $losers = settle_vacancy($pdo, (int)$vac['id'], $byUserId);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'student_id' => (int)$pl['student_id'], 'vacancy_id' => (int)$pl['vacancy_id'], 'losers' => $losers];
}
// The Ministry declines a pending placement: the slot is freed and the vacancy reopens.
function decline_placement(int $placementId, int $byUserId, string $reason): array {
    $pdo = db();
    $fail = function (string $m) use ($pdo): array {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $m];
    };
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT * FROM placements WHERE id = ? FOR UPDATE');
        $q->execute([$placementId]);
        $pl = $q->fetch();
        if (!$pl) return $fail('Placement not found.');
        if ($pl['status'] !== 'pending') return $fail('This placement is not waiting for approval.');
        $q = $pdo->prepare('SELECT * FROM vacancies WHERE id = ? FOR UPDATE');
        $q->execute([$pl['vacancy_id']]);
        $q->fetch();
        $pdo->prepare('DELETE FROM placements WHERE id = ?')->execute([$placementId]);
        $pdo->prepare("UPDATE vacancies SET slots_filled = GREATEST(slots_filled - 1, 0), status = IF(status = 'filled' AND closes_on >= CURDATE(), 'open', status) WHERE id = ?")
            ->execute([$pl['vacancy_id']]);
        $pdo->prepare("UPDATE applications SET status = 'unsuccessful', decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")
            ->execute([$reason, $byUserId, $pl['application_id']]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'student_id' => (int)$pl['student_id'], 'vacancy_id' => (int)$pl['vacancy_id']];
}
