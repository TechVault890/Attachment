#!/usr/bin/env bash
# Modules 4+5 test (applications, matching, placement, notifications): bash tests/smoke_module4.sh [base_url]
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
TMP=$(mktemp -d)
N=$RANDOM; PW='Smoke@12345'
UA="smoke${N}a"; UB="smoke${N}b"; UC="smoke${N}c"; MU="smin$N"; OU="sminx$N"; AU="sadm$N"
TODAY=$(date +%F); NEXT7=$(date -d '+7 days' +%F); NEXT30=$(date -d '+30 days' +%F); NEXT60=$(date -d '+60 days' +%F)
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
J(){ local n=$1; shift; curl -s -c "$TMP/$n.jar" -b "$TMP/$n.jar" "$@"; }
code(){ local n=$1; shift; J "$n" -o /dev/null -w '%{http_code}' "$@"; }
post(){ local n=$1 route=$2 T; shift 2; T=$(J "$n" "$(R vacancy/browse)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R "$route")" --data-urlencode "_csrf=$T" "$@"; }
login(){ local n=$1 user=$2 T; T=$(J "$n" "$(R auth/login)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" --data-urlencode "identity=$user" --data-urlencode "password=$PW"; }
register(){ local n=$1 user=$2 name=$3 reg=$4 county=$5 period=$6 T; T=$(J "$n" "$(R auth/register)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R auth/register)" --data-urlencode "_csrf=$T" --data-urlencode "full_name=$name" \
    --data-urlencode "username=$user" --data-urlencode "email=$user@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" \
    --data-urlencode "reg_no=$reg" -d "institution_id=1&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=$county&period_months=$period"; }
vac(){ local n=$1 title=$2 county=$3 slots=$4 dur=$5 T; T=$(J "$n" "$(R vacancy/browse)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R vacancy/create)" --data-urlencode "_csrf=$T" --data-urlencode "title=$title" \
    -d "department=ICT&office_location=Test+Office&county_id=$county&course_area=ICT+%2F+Software+Development&slots_total=$slots&duration_months=$dur&opens_on=$TODAY&closes_on=$NEXT30"; }
q(){ php tests/dbq.php "$1"; }
appq(){ q "SELECT a.$1 FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$2' AND a.vacancy_id = $3"; }
appcount(){ q "SELECT COUNT(*) FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$1' AND a.vacancy_id = $2"; }

php tests/make_user.php admin "$AU" "$PW" 0 > /dev/null
php tests/make_user.php ministry "$MU" "$PW" 1 > /dev/null
php tests/make_user.php ministry "$OU" "$PW" 2 > /dev/null
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > "$TMP/ok.pdf"

echo "--- setup ---"
check "Admin login" 302 "$(login adm $AU)"
check "Ministry login" 302 "$(login m $MU)"
check "Other-ministry login" 302 "$(login o $OU)"
check "Create application window" 302 "$(post adm window/save --data-urlencode "name=SMOKE Window $N" -d "opens_on=$TODAY&closes_on=$NEXT60")"
WID=$(php tests/lookup.php window "SMOKE Window $N")
check "Post vacancy Alpha (Nairobi, 1 slot, 3 months)" 302 "$(vac m "SMOKE Alpha $N" 47 1 3)"
check "Post vacancy Beta (Kiambu, 2 slots, 3 months)" 302 "$(vac m "SMOKE Beta $N" 22 2 3)"
V1=$(php tests/lookup.php vacancy "SMOKE Alpha $N"); V2=$(php tests/lookup.php vacancy "SMOKE Beta $N")
check "Register student A (Nairobi, 3 months)" 302 "$(register a $UA 'Smoke Tester A' "SMKA/$N" 47 3)"
check "Register student B (Kiambu, 3 months)" 302 "$(register b $UB 'Smoke Tester B' "SMKB/$N" 22 3)"
check "Register student C (Nairobi, 6 months)" 302 "$(register c $UC 'Smoke Tester C' "SMKC/$N" 47 6)"
check "Login A" 302 "$(login a $UA)"; check "Login B" 302 "$(login b $UB)"; check "Login C" 302 "$(login c $UC)"

echo "--- eligibility ---"
post a application/apply -d "vacancy_id=$V1" > /dev/null
check "Unverified student cannot apply" 0 "$(appcount $UA $V1)"
check "Unverified student sees why" 1 "$(atleast1 "$(J a "$(R vacancy/view)&id=$V1" | grep -c 'Only verified students')")"
T=$(J c "$(R student/documents)" | token)
check "Student C uploads a CV" 302 "$(J c -o /dev/null -w '%{http_code}' -X POST "$(R student/upload)" -F "_csrf=$T" -F "doc_type=cv" -F "file=@$TMP/ok.pdf")"
for u in $UA $UB $UC; do php tests/verify_student.php "$u"; done

echo "--- applying and matching ---"
check "A applies to Alpha" 302 "$(post a application/apply -d "vacancy_id=$V1")"
post a application/apply -d "vacancy_id=$V1" > /dev/null
check "Duplicate application ignored (still one row)" 1 "$(appcount $UA $V1)"
check "B applies to Alpha" 302 "$(post b application/apply -d "vacancy_id=$V1")"
check "C applies to Alpha" 302 "$(post c application/apply -d "vacancy_id=$V1")"
check "A applies to Beta" 302 "$(post a application/apply -d "vacancy_id=$V2")"
check "Three applications on Alpha" 3 "$(q "SELECT COUNT(*) FROM applications WHERE vacancy_id=$V1")"
check "Match score A on Alpha = 100" 100 "$(appq match_score $UA $V1)"
check "Match score C on Alpha = 90" 90 "$(appq match_score $UC $V1)"
check "Match score B on Alpha = 70" 70 "$(appq match_score $UB $V1)"
check "Ministry notified of applications" 1 "$(atleast1 "$(J m "$(R notification/index)" | grep -c 'Smoke Tester A applied')")"
check "Student C matches page lists Beta" 1 "$(atleast1 "$(J c "$(R application/matches)" | grep -c "SMOKE Beta $N")")"
check "Matches page excludes already-applied Alpha" 0 "$(J c "$(R application/matches)" | grep -c "SMOKE Alpha $N")"

echo "--- ministry view and access control ---"
check "Applicants page needs login" 302 "$(curl -s -o /dev/null -w '%{http_code}' "$(R application/applicants)&vacancy=$V1")"
check "Student cannot open applicants page" 403 "$(code a "$(R application/applicants)&vacancy=$V1")"
check "Other ministry cannot open applicants page" 404 "$(code o "$(R application/applicants)&vacancy=$V1")"
check "Admin can open applicants page" 200 "$(code adm "$(R application/applicants)&vacancy=$V1")"
PG=$(J m "$(R application/applicants)&vacancy=$V1")
pa=$(echo "$PG" | grep -n "SMKA/$N" | head -1 | cut -d: -f1)
pb=$(echo "$PG" | grep -n "SMKB/$N" | head -1 | cut -d: -f1)
pc=$(echo "$PG" | grep -n "SMKC/$N" | head -1 | cut -d: -f1)
check "Ranking: A (100) above C (90)" 1 "$([ -n "$pa" ] && [ -n "$pc" ] && [ "$pa" -lt "$pc" ] && echo 1 || echo 0)"
check "Ranking: C (90) above B (70)" 1 "$([ -n "$pc" ] && [ -n "$pb" ] && [ "$pc" -lt "$pb" ] && echo 1 || echo 0)"
check "One applicant suggested for the single slot" 1 "$(echo "$PG" | grep -c 'badge-suggested')"
DOC=$(q "SELECT d.id FROM documents d JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$UC' AND d.doc_type = 'cv'")
check "Ministry downloads an applicant's document" 200 "$(code m "$(R document/download)&id=$DOC")"
check "Other ministry blocked from that document" 403 "$(code o "$(R document/download)&id=$DOC")"

echo "--- decisions ---"
APPA1=$(appq id $UA $V1); APPB1=$(appq id $UB $V1); APPC1=$(appq id $UC $V1)
check "Student cannot decide on applications" 403 "$(post a application/decide -d "id=$APPB1&action=shortlist")"
check "Shortlist C" 302 "$(post m application/decide -d "id=$APPC1&action=shortlist")"
check "C is shortlisted" shortlisted "$(appq status $UC $V1)"
check "C was notified" 1 "$(atleast1 "$(J c "$(R notification/index)" | grep -ci 'shortlisted')")"
check "Reject without a reason redirects" 302 "$(post m application/decide -d "id=$APPB1&action=reject" --data-urlencode "note=")"
check "Reject without a reason shows an error" 1 "$(atleast1 "$(J m "$(R application/applicants)&vacancy=$V1" | grep -c 'alert-danger')")"
check "B still applied" applied "$(appq status $UB $V1)"
check "Reject B with a reason" 302 "$(post m application/decide -d "id=$APPB1&action=reject" --data-urlencode "note=Insufficient experience for this role")"
check "B is unsuccessful" unsuccessful "$(appq status $UB $V1)"
NB=$(J b "$(R notification/index)")
check "B was told the reason" 1 "$(atleast1 "$(echo "$NB" | grep -c 'Insufficient experience')")"
check "B was shown other matching vacancies (Beta)" 1 "$(atleast1 "$(echo "$NB" | grep -c "SMOKE Beta $N")")"
check "Other ministry cannot decide on Alpha applicant" 404 "$(post o application/decide -d "id=$APPA1&action=shortlist")"

echo "--- withdraw and reapply ---"
check "B applies to Beta" 302 "$(post b application/apply -d "vacancy_id=$V2")"
APPB2=$(appq id $UB $V2)
check "B withdraws from Beta" 302 "$(post b application/withdraw -d "id=$APPB2")"
check "B's Beta application is withdrawn" withdrawn "$(appq status $UB $V2)"
check "B reapplies to Beta" 302 "$(post b application/apply -d "vacancy_id=$V2")"
check "B's Beta application is active again, one row" "applied|1" "$(appq status $UB $V2)|$(appcount $UB $V2)"

echo "--- placement ---"
check "Place A with supervisor details" 302 "$(post m application/decide -d "id=$APPA1&action=place" --data-urlencode "supervisor_name=Jane Officer" -d "supervisor_phone=0711000000" -d "start_date=$NEXT7")"
check "A is placed" placed "$(appq status $UA $V1)"
check "Alpha is now filled" "filled|1" "$(q "SELECT status FROM vacancies WHERE id=$V1")|$(q "SELECT slots_filled FROM vacancies WHERE id=$V1")"
check "Placement has computed end date and supervisor" 1 "$(q "SELECT COUNT(*) FROM placements WHERE vacancy_id=$V1 AND end_date IS NOT NULL AND supervisor_name='Jane Officer'")"
check "A's other application (Beta) auto-withdrawn" withdrawn "$(appq status $UA $V2)"
check "C (shortlisted) auto-marked unsuccessful" unsuccessful "$(appq status $UC $V1)"
check "C told the slots were filled" 1 "$(atleast1 "$(J c "$(R notification/index)" | grep -ci 'filled')")"
check "A notified of placement" 1 "$(atleast1 "$(J a "$(R notification/index)" | grep -c 'Placement confirmed')")"
check "A dashboard shows the placement" 1 "$(atleast1 "$(J a "$(R dashboard/student)" | grep -c "SMOKE Alpha $N")")"
check "A applications page shows the supervisor" 1 "$(atleast1 "$(J a "$(R application/mine)" | grep -c 'Jane Officer')")"
check "Placing into a filled vacancy is refused" 302 "$(post m application/decide -d "id=$APPC1&action=place")"
check "Still exactly one placement on Alpha" 1 "$(q "SELECT COUNT(*) FROM placements WHERE vacancy_id=$V1")"
post a application/apply -d "vacancy_id=$V2" > /dev/null
check "Placed student cannot apply again" withdrawn "$(appq status $UA $V2)"

echo "--- window and closed-vacancy rules ---"
post adm window/toggle -d "id=$WID" > /dev/null
if curl -s "$(R vacancy/browse)" | grep -q 'window-open'; then
  echo "SKIP: another application window is active, so the no-window check was skipped"
else
  post c application/apply -d "vacancy_id=$V2" > /dev/null
  check "No application without an open window" 0 "$(appcount $UC $V2)"
fi
post adm window/toggle -d "id=$WID" > /dev/null
check "Ministry closes Beta" 302 "$(post m vacancy/status -d "id=$V2&to=close")"
post c application/apply -d "vacancy_id=$V2" > /dev/null
check "Cannot apply to a closed vacancy" 0 "$(appcount $UC $V2)"

echo "--- notifications UI ---"
check "Notifications page loads" 200 "$(code a "$(R notification/index)")"
check "Unread count shown in the header" 1 "$(atleast1 "$(J a "$(R vacancy/browse)" | grep -c 'class="count"')")"
check "Mark all as read" 302 "$(post a notification/read -d "all=1")"
check "Unread count cleared" 0 "$(J a "$(R vacancy/browse)" | grep -c 'class="count"')"

echo "Result: $ok passed, $bad failed (test users: $UA, $UB, $UC, $MU, $OU, $AU)"
php tests/cleanup.php
rm -rf "$TMP"
