<?php
require_once __DIR__ . '/../models/student.php';

function student_profile(): void {
    $u = require_role('student');
    $s = get_student_by_user((int)$u['id']);
    if (!$s) not_found();
    $locked = $s['verification_status'] === 'verified';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($locked) {
            flash('warning', 'Your profile is verified and locked. Contact your coordinator to change it.');
            redirect('student/profile');
        }
        $f = [];
        foreach (['phone', 'course', 'area_of_interest', 'preferred_county_id', 'period_months', 'available_from', 'available_to'] as $k) {
            $f[$k] = trim((string)($_POST[$k] ?? ''));
        }
        if ($f['phone'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $f['phone'])) $errors[] = 'Enter a valid phone number.';
        if (mb_strlen($f['course']) < 3 || mb_strlen($f['course']) > 150) $errors[] = 'Enter your attachment course.';
        if (!in_array($f['area_of_interest'], areas(), true)) $errors[] = 'Choose an area of interest.';
        if (!in_array($f['period_months'], ['3', '6', '12'], true)) $errors[] = 'Choose a period of 3, 6 or 12 months.';
        $chk = db()->prepare('SELECT COUNT(*) FROM counties WHERE id = ?');
        $chk->execute([(int)$f['preferred_county_id']]);
        if (!$chk->fetchColumn()) $errors[] = 'Choose your preferred county.';
        foreach (['available_from', 'available_to'] as $k) {
            if ($f[$k] !== '' && !valid_date($f[$k])) $errors[] = 'Enter valid dates.';
        }
        if (!$errors && $f['available_from'] !== '' && $f['available_to'] !== '' && $f['available_to'] < $f['available_from']) {
            $errors[] = 'The end date must be after the start date.';
        }
        if (!$errors) {
            $pdo = db();
            $newStatus = $s['verification_status'] === 'rejected' ? 'pending' : $s['verification_status'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE students SET course=?, area_of_interest=?, preferred_county_id=?, period_months=?, available_from=?, available_to=?, verification_status=?, verification_reason=? WHERE id=?')
                ->execute([$f['course'], $f['area_of_interest'], (int)$f['preferred_county_id'], (int)$f['period_months'],
                    $f['available_from'] ?: null, $f['available_to'] ?: null, $newStatus,
                    $newStatus === 'pending' ? null : $s['verification_reason'], $s['id']]);
            $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$f['phone'] !== '' ? $f['phone'] : null, $u['id']]);
            $pdo->commit();
            audit('update_profile', 'student', (int)$s['id']);
            if ($s['verification_status'] === 'rejected') {
                notify_coordinators((int)$s['institution_id'], 'verification', 'Student resubmitted details',
                    $s['full_name'] . ' (' . $s['reg_no'] . ') updated their profile after rejection.',
                    url('coordinator/review', ['id' => $s['id']]));
            }
            flash('success', 'Profile updated.');
            redirect('student/profile');
        }
        $_SESSION['old'] = $f;
    }
    if (!isset($_SESSION['old'])) {
        $_SESSION['old'] = [
            'phone' => (string)$s['phone'], 'course' => $s['course'], 'area_of_interest' => $s['area_of_interest'],
            'preferred_county_id' => (string)$s['preferred_county_id'], 'period_months' => (string)$s['period_months'],
            'available_from' => (string)$s['available_from'], 'available_to' => (string)$s['available_to'],
        ];
    }
    view('student/profile', ['title' => 'My profile', 's' => $s, 'errors' => $errors, 'locked' => $locked,
        'counties' => counties(), 'areas' => areas()]);
}

function student_documents(): void {
    $u = require_role('student');
    $s = get_student_by_user((int)$u['id']);
    if (!$s) not_found();
    view('student/documents', ['title' => 'My documents', 's' => $s, 'docs' => get_documents((int)$s['id']),
        'types' => doc_types(), 'locked' => $s['verification_status'] === 'verified']);
}

function student_upload(): void {
    $u = require_role('student');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('student/documents');
    $s = get_student_by_user((int)$u['id']);
    if (!$s) not_found();
    $fail = function (string $m): never { flash('danger', $m); redirect('student/documents'); };

    if ($s['verification_status'] === 'verified') {
        flash('warning', 'Your documents are locked because your profile is verified.');
        redirect('student/documents');
    }
    $type = (string)($_POST['doc_type'] ?? '');
    if (!array_key_exists($type, doc_types())) $fail('Unknown document type.');

    $f = $_FILES['file'] ?? null;
    $err = is_array($f) ? ($f['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        if ($err === UPLOAD_ERR_NO_FILE) $fail('Choose a file to upload.');
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) $fail('File is too large (maximum 2 MB).');
        $fail('Upload failed. Please try again.');
    }
    $cfg = config()['uploads'];
    if ($f['size'] < 1 || $f['size'] > $cfg['max_size']) $fail('File must be between 1 byte and 2 MB.');

    $orig = basename(str_replace('\\', '/', (string)$f['name']));
    $orig = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $orig), 0, 200);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $rules = upload_rules();
    if (!isset($rules[$ext])) $fail('Only PDF, DOC or DOCX files are allowed.');
    $detected = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($detected, $rules[$ext]['detect'], true)) $fail('The file content does not match its type. Upload a real PDF, DOC or DOCX.');
    if ($ext === 'pdf' && file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') $fail('That PDF file looks damaged.');

    $dir = $cfg['dir'];
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_dir($dir) || !is_writable($dir)) $fail('The server upload folder is not writable. Contact the administrator.');
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . DIRECTORY_SEPARATOR . $stored)) $fail('Could not save the file. Please try again.');

    $pdo = db();
    $q = $pdo->prepare('SELECT stored_name FROM documents WHERE student_id = ? AND doc_type = ?');
    $q->execute([$s['id'], $type]);
    $old = $q->fetchColumn();
    $before = count(get_documents((int)$s['id']));
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM documents WHERE student_id = ? AND doc_type = ?')->execute([$s['id'], $type]);
    $pdo->prepare('INSERT INTO documents (student_id, doc_type, original_name, stored_name, mime_type, size_bytes) VALUES (?,?,?,?,?,?)')
        ->execute([$s['id'], $type, $orig, $stored, $rules[$ext]['mime'], (int)$f['size']]);
    $pdo->commit();
    if ($old && preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', (string)$old)) {
        @unlink($dir . DIRECTORY_SEPARATOR . $old);
    }
    audit('upload_document', 'student', (int)$s['id'], $type);
    if ($before < 4 && count(get_documents((int)$s['id'])) === 4) {
        notify_coordinators((int)$s['institution_id'], 'verification', 'Documents complete',
            $s['full_name'] . ' (' . $s['reg_no'] . ') has uploaded all four documents.',
            url('coordinator/review', ['id' => $s['id']]));
    }
    flash('success', doc_types()[$type] . ' uploaded.');
    redirect('student/documents');
}
