<?php
// Usage:  php bin/demo_seed.php            create demo data (refuses if it already exists)
//         php bin/demo_seed.php --reset    delete the demo data, then create it again
//         php bin/demo_seed.php --remove   delete the demo data only
//         add --force to run when app.env in config.php is not "development"
if (PHP_SAPI !== 'cli') { exit("CLI only.\n"); }
require __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/models/application.php';

const DEMO_PW = 'Demo@12345';
$flags = array_slice($argv, 1);
$pdo = db();
$dir = config()['uploads']['dir'];

function demo_remove(PDO $pdo, string $dir): void {
    $files = $pdo->query("SELECT d.stored_name FROM documents d JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id WHERE LEFT(u.username, 5) = 'demo_'")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec("DELETE p FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id WHERE LEFT(u.username, 5) = 'demo_'");
    $pdo->exec("DELETE FROM users WHERE LEFT(username, 5) = 'demo_'");
    foreach ($files as $f) {
        if (preg_match('/^[a-f0-9]{32}\.pdf$/', $f)) @unlink($dir . DIRECTORY_SEPARATOR . $f);
    }
    try {
        $pdo->exec("DELETE FROM vacancies WHERE description LIKE '%[demo]'");
    } catch (PDOException $e) {
        echo "Note: some demo vacancies are used by real applications and were kept.\n";
    }
    $pdo->exec("DELETE FROM application_windows WHERE name = 'Demo Intake'");
}
function demo_id(PDO $pdo, string $sql, array $p, string $what): int {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $id = $st->fetchColumn();
    if (!$id) exit("Cannot find $what in the database. Did you import database/seed.sql?\n");
    return (int)$id;
}
function demo_user(PDO $pdo, string $role, string $username, string $name, ?int $inst, ?int $min): int {
    $pdo->prepare('INSERT INTO users (username, email, password_hash, role, full_name, institution_id, ministry_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$username, $username . '@demo.example.com', password_hash(DEMO_PW, PASSWORD_DEFAULT), $role, $name, $inst, $min]);
    return (int)$pdo->lastInsertId();
}
function demo_docs(PDO $pdo, string $dir, int $studentId): void {
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $body = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    foreach (doc_types() as $type => $label) {
        $stored = bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $body);
        $pdo->prepare('INSERT INTO documents (student_id, doc_type, original_name, stored_name, mime_type, size_bytes) VALUES (?,?,?,?,?,?)')
            ->execute([$studentId, $type, str_replace('_', '-', $type) . '.pdf', $stored, 'application/pdf', strlen($body)]);
    }
}
function demo_apply(PDO $pdo, int $studentId, int $vacancyId): int {
    $s = get_student_by_id($studentId);
    $v = get_vacancy($vacancyId);
    $pdo->prepare('INSERT INTO applications (student_id, vacancy_id, match_score) VALUES (?,?,?)')
        ->execute([$studentId, $vacancyId, match_score($s, $v)]);
    return (int)$pdo->lastInsertId();
}

if (in_array('--remove', $flags, true)) {
    demo_remove($pdo, $dir);
    exit("Demo data removed.\n");
}
if ((config()['app']['env'] ?? '') !== 'development' && !in_array('--force', $flags, true)) {
    exit("Refusing to create demo data because app.env is not 'development'. Use --force if you really want to.\n");
}
$exists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE LEFT(username, 5) = 'demo_'")->fetchColumn();
if ($exists && !in_array('--reset', $flags, true)) {
    exit("Demo data already exists. Run: php bin/demo_seed.php --reset\n");
}
if ($exists) demo_remove($pdo, $dir);

$mku = demo_id($pdo, "SELECT id FROM institutions WHERE code = 'MKU'", [], 'the institution with code MKU');
$min = [];
foreach (['MOE', 'ICT', 'MOH'] as $abbr) {
    $min[$abbr] = demo_id($pdo, 'SELECT id FROM ministries WHERE abbreviation = ?', [$abbr], "the Ministry with abbreviation $abbr");
}
$coord = demo_user($pdo, 'coordinator', 'demo_coord', 'Grace Wambui', $mku, null);
$officer = [
    'MOE' => demo_user($pdo, 'ministry', 'demo_moe', 'John Kamau', null, $min['MOE']),
    'ICT' => demo_user($pdo, 'ministry', 'demo_ict', 'Lucy Njoroge', null, $min['ICT']),
    'MOH' => demo_user($pdo, 'ministry', 'demo_moh', 'Peter Mutiso', null, $min['MOH']),
];

if (!active_window()) {
    $pdo->prepare('INSERT INTO application_windows (name, opens_on, closes_on) VALUES (?,?,?)')
        ->execute(['Demo Intake', date('Y-m-d'), date('Y-m-d', strtotime('+90 days'))]);
    $windowNote = 'Created application window "Demo Intake" (today to +90 days).';
} else {
    $windowNote = 'An application window is already active, so none was created.';
}

// key => [ministry, title, department, office, county, area, slots, months]
$vacDefs = [
    'ict_support' => ['MOE', 'ICT Support Intern', 'ICT Directorate', 'Jogoo House, Harambee Avenue', 'Nairobi', 'ICT / Software Development', 3, 3],
    'software'    => ['ICT', 'Software Development Attachment', 'Digital Economy', 'Konza Technopolis Office', 'Machakos', 'ICT / Software Development', 2, 6],
    'data'        => ['ICT', 'Data and Statistics Intern', 'Data Services', 'ICT Authority Offices', 'Nairobi', 'Statistics & Data', 2, 3],
    'records'     => ['MOH', 'Health Records Intern', 'Health Information', 'Mombasa County Referral Office', 'Mombasa', 'Health Sciences', 2, 3],
    'hr'          => ['MOE', 'Human Resource Intern', 'Human Resources', 'Education Office Kisumu', 'Kisumu', 'Human Resource Management', 1, 3],
    'accounts'    => ['MOE', 'Accounts Intern', 'Finance', 'Kiambu Sub-County Office', 'Kiambu', 'Accounting & Finance', 2, 3],
    'admin'       => ['MOH', 'Public Administration Intern', 'Administration', 'Nakuru County Health Office', 'Nakuru', 'Public Administration', 2, 6],
    'comms'       => ['ICT', 'Communications Intern', 'Public Communications', 'Eldoret ICT Hub', 'Uasin Gishu', 'Communication & Media', 1, 3],
];
$vac = [];
foreach ($vacDefs as $key => [$m, $title, $dept, $office, $county, $area, $slots, $months]) {
    $countyId = demo_id($pdo, 'SELECT id FROM counties WHERE name = ?', [$county], "the county $county");
    $pdo->prepare('INSERT INTO vacancies (ministry_id, posted_by, title, department, office_location, county_id, course_area, description, slots_total, duration_months, opens_on, closes_on) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$min[$m], $officer[$m], $title, $dept, $office, $countyId, $area,
            'Hands-on attachment in the ' . $dept . '. [demo]', $slots, $months, date('Y-m-d'), date('Y-m-d', strtotime('+45 days'))]);
    $vac[$key] = (int)$pdo->lastInsertId();
}

// username => [name, reg no, area, county, months, state, vacancy to apply for]
$studentDefs = [
    'demo_amina'   => ['Amina Wanjiru',   'MKU/BBIT/001', 'ICT / Software Development', 'Nairobi',     3, 'placed',        'ict_support'],
    'demo_brian'   => ['Brian Otieno',    'MKU/BBIT/002', 'ICT / Software Development', 'Nairobi',     3, 'verified',      'ict_support'],
    'demo_cynthia' => ['Cynthia Achieng', 'MKU/BBIT/003', 'Accounting & Finance',       'Kiambu',      3, 'verified',      'hr'],
    'demo_daniel'  => ['Daniel Kiprop',   'MKU/BBIT/004', 'Public Administration',      'Nakuru',      6, 'pending_ready', null],
    'demo_esther'  => ['Esther Mwende',   'MKU/BBIT/005', 'Health Sciences',            'Mombasa',     3, 'pending_ready', null],
    'demo_faith'   => ['Faith Njeri',     'MKU/BBIT/006', 'Statistics & Data',          'Nairobi',     3, 'pending_docs',  null],
    'demo_george'  => ['George Mutua',    'MKU/BBIT/007', 'Human Resource Management',  'Kisumu',      3, 'rejected',      null],
    'demo_halima'  => ['Halima Abdi',     'MKU/BBIT/008', 'Communication & Media',      'Uasin Gishu', 3, 'verified',      'comms'],
];
$sid = []; $uid = []; $appId = [];
foreach ($studentDefs as $username => [$name, $reg, $area, $county, $months, $state, $applyTo]) {
    $countyId = demo_id($pdo, 'SELECT id FROM counties WHERE name = ?', [$county], "the county $county");
    $uid[$username] = demo_user($pdo, 'student', $username, $name, $mku, null);
    $status = in_array($state, ['placed', 'verified'], true) ? 'verified' : ($state === 'rejected' ? 'rejected' : 'pending');
    $decided = $status !== 'pending';
    $pdo->prepare('INSERT INTO students (user_id, institution_id, reg_no, course, area_of_interest, preferred_county_id, period_months, verification_status, verification_reason, verified_by, verified_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$uid[$username], $mku, $reg, 'BBIT', $area, $countyId, $months, $status,
            $state === 'rejected' ? 'Recommendation letter is not signed by the school' : null,
            $decided ? $coord : null, $decided ? date('Y-m-d H:i:s') : null]);
    $sid[$username] = (int)$pdo->lastInsertId();
    if ($state !== 'pending_docs') demo_docs($pdo, $dir, $sid[$username]);
    if ($state === 'pending_ready') {
        notify($coord, 'verification', 'Documents complete', $name . ' (' . $reg . ') has uploaded all four documents.',
            url('coordinator/review', ['id' => $sid[$username]]));
    }
    if ($applyTo) {
        $appId[$username] = demo_apply($pdo, $sid[$username], $vac[$applyTo]);
        if ($state !== 'placed') {   // Amina is placed by hand below; everyone else goes through the real rules
            $sv = get_student_by_id($sid[$username]);
            $vv = get_vacancy($vac[$applyTo]);
            $off = $officer[$vacDefs[$applyTo][0]];
            $r = auto_placement_ok(match_breakdown($sv, $vv)) ? reserve_placement($appId[$username], null, null) : ['ok' => false];
            if ($r['ok']) {
                notify($uid[$username], 'placement', 'Placement reserved',
                    'You have been placed at ' . $vv['ministry'] . ' for "' . $vv['title'] . '". The Ministry must still approve it.', url('application/mine'));
                notify($off, 'placement', 'Placement awaiting your approval',
                    $name . ' was placed automatically for "' . $vv['title'] . '". Please approve or decline.',
                    url('application/applicants', ['vacancy' => $vac[$applyTo]]));
            } else {
                notify($off, 'application', 'New application', $name . ' applied for "' . $vv['title'] . '". It needs your review.',
                    url('application/applicants', ['vacancy' => $vac[$applyTo]]));
            }
        }
    }
}

$res = confirm_placement($appId['demo_amina'], $officer['MOE'], [
    'start_date' => date('Y-m-d', strtotime('+14 days')),
    'supervisor_name' => 'Mr. Samuel Kariuki', 'supervisor_phone' => '0722111222', 'supervisor_email' => '',
]);
if (!$res['ok']) exit('Could not create the demo placement: ' . $res['msg'] . "\n");
notify($uid['demo_amina'], 'placement', 'Placement confirmed',
    'You have been placed at the Ministry of Education (Jogoo House, Harambee Avenue, Nairobi) for "ICT Support Intern".', url('application/mine'));

echo "Demo data ready.\n$windowNote\n";
echo "Password for every demo account: " . DEMO_PW . "\n\n";
echo "  demo_coord                      coordinator, Mount Kenya University\n";
echo "  demo_moe / demo_ict / demo_moh  Ministry officers\n";
echo "  demo_amina    placement approved\n";
echo "  demo_brian    placed automatically, awaiting demo_moe's approval\n";
echo "  demo_halima   placed automatically, awaiting demo_ict's approval\n";
echo "  demo_cynthia  applied, different area: waits for demo_moe's decision\n";
echo "  demo_daniel, demo_esther        pending, documents uploaded (awaiting the coordinator)\n";
echo "  demo_faith    pending, no documents      demo_george  rejected\n";
echo "\nRemove it all later with: php bin/demo_seed.php --remove\n";
