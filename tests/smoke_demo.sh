#!/usr/bin/env bash
# Demo data test: bash tests/smoke_demo.sh [base_url]   (rebuilds the demo data and LEAVES it in place)
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
TMP=$(mktemp -d); PW='Demo@12345'
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
J(){ local n=$1; shift; curl -s -c "$TMP/$n.jar" -b "$TMP/$n.jar" "$@"; }
code(){ local n=$1; shift; J "$n" -o /dev/null -w '%{http_code}' "$@"; }
login(){ local n=$1 user=$2 T; T=$(J "$n" "$(R auth/login)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" --data-urlencode "identity=$user" --data-urlencode "password=$PW"; }
q(){ php tests/dbq.php "$1"; }

OUT=$(php bin/demo_seed.php --reset 2>&1)
check "Seed script finished" 1 "$(echo "$OUT" | grep -c 'Demo data ready')"
check "Four demo staff accounts" 4 "$(q "SELECT COUNT(*) FROM users WHERE LEFT(username,5)='demo_' AND role <> 'student'")"
check "Eight demo students" 8 "$(q "SELECT COUNT(*) FROM users WHERE LEFT(username,5)='demo_' AND role = 'student'")"
check "Eight demo vacancies" 8 "$(q "SELECT COUNT(*) FROM vacancies WHERE description LIKE '%[demo]'")"
check "Three demo placements (Amina approved, Brian and Halima pending)" 3 "$(q "SELECT COUNT(*) FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id WHERE LEFT(u.username,5)='demo_'")"
VID=$(q "SELECT id FROM vacancies WHERE title = 'ICT Support Intern' AND description LIKE '%[demo]' ORDER BY id DESC LIMIT 1")
VCOM=$(q "SELECT id FROM vacancies WHERE title = 'Communications Intern' AND description LIKE '%[demo]' ORDER BY id DESC LIMIT 1")
VHR=$(q "SELECT id FROM vacancies WHERE title = 'Human Resource Intern' AND description LIKE '%[demo]' ORDER BY id DESC LIMIT 1")
check "ICT Support vacancy has two slots taken" 2 "$(q "SELECT slots_filled FROM vacancies WHERE id = $VID")"
check "Re-running without --reset refuses" 1 "$(php bin/demo_seed.php 2>&1 | grep -c 'already exists')"

check "Coordinator login" 302 "$(login c demo_coord)"
check "Ministry officer login (MOE)" 302 "$(login m demo_moe)"
check "Other Ministry officer login (ICT)" 302 "$(login o demo_ict)"
check "Amina login" 302 "$(login a demo_amina)"
check "Brian login" 302 "$(login b demo_brian)"
check "Pending queue lists Daniel" 1 "$(atleast1 "$(J c "$(R coordinator/queue)" | grep -c 'Daniel Kiprop')")"
check "Placed list shows Amina's supervisor" 1 "$(atleast1 "$(J c "$(R coordinator/placed)" | grep -c 'Samuel Kariuki')")"
check "Placed list hides Brian (pending)" 0 "$(J c "$(R coordinator/placed)" | grep -c 'Brian Otieno')"
check "Amina's dashboard shows her placement" 1 "$(atleast1 "$(J a "$(R dashboard/student)" | grep -c 'Your placement')")"
check "Brian's dashboard says awaiting approval" 1 "$(atleast1 "$(J b "$(R dashboard/student)" | grep -c 'awaiting Ministry approval')")"
check "MOE officer sees the approval section" 1 "$(atleast1 "$(J m "$(R application/applicants)&vacancy=$VID" | grep -c 'Awaiting your approval')")"
check "MOE officer sees Brian waiting for approval" 1 "$(atleast1 "$(J m "$(R application/applicants)&vacancy=$VID" | grep -c 'Brian Otieno')")"
check "Other Ministry cannot see those applicants" 404 "$(code o "$(R application/applicants)&vacancy=$VID")"
check "ICT officer sees Halima awaiting approval" 1 "$(atleast1 "$(J o "$(R application/applicants)&vacancy=$VCOM" | grep -c 'Halima Abdi')")"
check "MOE officer sees Cynthia waiting for a decision" 1 "$(atleast1 "$(J m "$(R application/applicants)&vacancy=$VHR" | grep -c 'Waiting for your decision')")"
check "Demo vacancy page loads" 200 "$(code a "$(R vacancy/view)&id=$VID")"
DID=$(q "SELECT d.id FROM documents d JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id WHERE u.username = 'demo_daniel' LIMIT 1")
check "Coordinator can download a demo document" 200 "$(code c "$(R document/download)&id=$DID")"

echo "Result: $ok passed, $bad failed. Demo data was left in place (remove with: php bin/demo_seed.php --remove)"
rm -rf "$TMP"
