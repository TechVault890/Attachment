#!/usr/bin/env bash
# Automatic first-come-first-served placement + Ministry approval: bash tests/smoke_auto_placement.sh [base_url]
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
TMP=$(mktemp -d)
N=$RANDOM; PW='Smoke@12345'
UA="smoke${N}a"; UB="smoke${N}b"; UC="smoke${N}c"; UD="smoke${N}d"; UE="smoke${N}e"; UF="smoke${N}f"
MU="smin$N"; OU="sminx$N"; AU="sadm$N"; CU="scoord$N"
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
vac(){ local n=$1 title=$2 county=$3 slots=$4 area=$5
  post "$n" vacancy/create --data-urlencode "title=$title" --data-urlencode "course_area=$area" \
    -d "department=ICT&office_location=Test+Office&county_id=$county&slots_total=$slots&duration_months=3&opens_on=$TODAY&closes_on=$NEXT30"; }
q(){ php tests/dbq.php "$1"; }
appq(){ q "SELECT a.$1 FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$2' AND a.vacancy_id = $3"; }
appcount(){ q "SELECT COUNT(*) FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$1' AND a.vacancy_id = $2"; }
pls(){ q "SELECT p.status FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$1'"; }
vq(){ q "SELECT $1 FROM vacancies WHERE id = $2"; }
approve(){ post m application/decide -d "id=$1&action=approve" "${@:2}"; }

php tests/make_user.php admin "$AU" "$PW" 0 > /dev/null
php tests/make_user.php ministry "$MU" "$PW" 1 > /dev/null
php tests/make_user.php ministry "$OU" "$PW" 2 > /dev/null
php tests/make_user.php coordinator "$CU" "$PW" 1 > /dev/null

echo "--- setup ---"
check "Admin login" 302 "$(login adm $AU)"
check "Ministry login" 302 "$(login m $MU)"
check "Other-ministry login" 302 "$(login o $OU)"
check "Coordinator login" 302 "$(login c $CU)"
check "Create application window" 302 "$(post adm window/save --data-urlencode "name=SMOKE Window $N" -d "opens_on=$TODAY&closes_on=$NEXT60")"
check "Vacancy Alpha (Nairobi, 1 slot, ICT)" 302 "$(vac m "SMOKE Alpha $N" 47 1 'ICT / Software Development')"
check "Vacancy Beta (Kiambu, 2 slots, ICT)" 302 "$(vac m "SMOKE Beta $N" 22 2 'ICT / Software Development')"
check "Vacancy Delta (Nairobi, 1 slot, Law)" 302 "$(vac m "SMOKE Delta $N" 47 1 'Law')"
VA=$(php tests/lookup.php vacancy "SMOKE Alpha $N"); VB=$(php tests/lookup.php vacancy "SMOKE Beta $N"); VD=$(php tests/lookup.php vacancy "SMOKE Delta $N")
check "Register A" 302 "$(register a $UA 'Smoke Tester A' "SMK8A/$N" 47 3)"
check "Register B" 302 "$(register b $UB 'Smoke Tester B' "SMK8B/$N" 22 3)"
check "Register C" 302 "$(register c $UC 'Smoke Tester C' "SMK8C/$N" 47 6)"
check "Register D" 302 "$(register d $UD 'Smoke Tester D' "SMK8D/$N" 47 3)"
check "Register E" 302 "$(register e $UE 'Smoke Tester E' "SMK8E/$N" 47 3)"
check "Register F" 302 "$(register f $UF 'Smoke Tester F' "SMK8F/$N" 47 3)"
for p in "a $UA" "b $UB" "c $UC" "d $UD" "e $UE" "f $UF"; do set -- $p; login $1 $2 > /dev/null; php tests/verify_student.php "$2"; done

echo "--- area mismatch waits for the Ministry ---"
check "D applies to the Law vacancy" 302 "$(post d application/apply -d "vacancy_id=$VD")"
post e application/apply -d "vacancy_id=$VD" > /dev/null
post f application/apply -d "vacancy_id=$VD" > /dev/null
check "D waits (status applied)" applied "$(appq status $UD $VD)"
check "D's score is 50 (county + period only)" 50 "$(appq match_score $UD $VD)"
check "D has no placement: a score of 50 without the area does not auto-place" 0 "$(pls $UD)"
check "E and F are waiting too" 2 "$(q "SELECT COUNT(*) FROM applications WHERE vacancy_id=$VD AND status='applied'")"

echo "--- automatic placement, first come first served ---"
check "A applies to Alpha" 302 "$(post a application/apply -d "vacancy_id=$VA")"
check "A is placed automatically" placed "$(appq status $UA $VA)"
check "A's placement is pending approval" pending "$(pls $UA)"
check "Alpha is filled with 1 slot taken" "filled|1" "$(vq status $VA)|$(vq slots_filled $VA)"
check "A told the placement is reserved" 1 "$(atleast1 "$(J a "$(R notification/index)" | grep -c 'Placement reserved')")"
check "Ministry told a placement awaits approval" 1 "$(atleast1 "$(J m "$(R notification/index)" | grep -c 'Placement awaiting your approval')")"
check "A's applications page shows awaiting approval" 1 "$(atleast1 "$(J a "$(R application/mine)" | grep -c 'Awaiting Ministry approval')")"
check "C cannot take the filled Alpha" 0 "$(post c application/apply -d "vacancy_id=$VA" > /dev/null; appcount $UC $VA)"
check "B applies to Beta" 302 "$(post b application/apply -d "vacancy_id=$VB")"
check "C applies to Beta (area matches, 6 months)" 302 "$(post c application/apply -d "vacancy_id=$VB")"
check "B and C are both pending" "pending|pending" "$(pls $UB)|$(pls $UC)"
check "Beta is full" "filled|2" "$(vq status $VB)|$(vq slots_filled $VB)"
check "Pending placements are hidden from the coordinator's list" 0 "$(J c "$(R coordinator/placed)" | grep -c "SMK8B/$N")"
check "Pending placements are not counted in reports" "$(q "SELECT COUNT(*) FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.county_id = 22 AND p.status <> 'pending'")" "$(J adm -o "$TMP/county.csv" "$(R report/export)&type=county"; php tests/csvcell.php "$TMP/county.csv" Kiambu 2 | sed 's/^$/0/')"

echo "--- access control ---"
APPA=$(appq id $UA $VA); APPB=$(appq id $UB $VB); APPC=$(appq id $UC $VB); APPD=$(appq id $UD $VD); APPE=$(appq id $UE $VD); APPF=$(appq id $UF $VD)
check "Student cannot approve" 403 "$(post b application/decide -d "id=$APPA&action=approve")"
check "Other Ministry cannot approve" 404 "$(post o application/decide -d "id=$APPA&action=approve")"
check "Placement still pending" pending "$(pls $UA)"
check "Applicants page lists the approval section" 1 "$(atleast1 "$(J m "$(R application/applicants)&vacancy=$VA" | grep -c 'Awaiting your approval')")"

echo "--- decline frees the slot ---"
check "Decline without a reason" 302 "$(post m application/decide -d "id=$APPA&action=decline" --data-urlencode "note=")"
check "Still pending after a blank decline" pending "$(pls $UA)"
check "Decline with a reason" 302 "$(post m application/decide -d "id=$APPA&action=decline" --data-urlencode "note=Position needs a different skill set")"
check "Placement removed" 0 "$(pls $UA)"
check "Alpha is open again with 0 slots taken" "open|0" "$(vq status $VA)|$(vq slots_filled $VA)"
check "A's application is unsuccessful" unsuccessful "$(appq status $UA $VA)"
check "A was told the reason" 1 "$(atleast1 "$(J a "$(R notification/index)" | grep -c 'Position needs a different skill set')")"

echo "--- first come first served after a decline ---"
check "D (waiting on Law) applies to Alpha" 302 "$(post d application/apply -d "vacancy_id=$VA")"
check "D is placed automatically" pending "$(pls $UD)"
check "D's waiting Law application was withdrawn" withdrawn "$(appq status $UD $VD)"
check "Alpha is filled again" "filled|1" "$(vq status $VA)|$(vq slots_filled $VA)"

echo "--- approval ---"
APPD2=$(appq id $UD $VA)
check "Approve with a bad supervisor email" 302 "$(approve $APPD2 --data-urlencode "supervisor_email=notanemail")"
check "Still pending after the bad email" pending "$(pls $UD)"
check "Approve D with supervisor details" 302 "$(approve $APPD2 -d "start_date=$NEXT7" --data-urlencode "supervisor_name=Sam Supervisor" -d "supervisor_phone=0722000000")"
check "D's placement is confirmed" confirmed "$(pls $UD)"
check "Supervisor and end date saved" 1 "$(q "SELECT COUNT(*) FROM placements WHERE supervisor_name='Sam Supervisor' AND end_date IS NOT NULL AND vacancy_id=$VA")"
check "D told the placement is confirmed" 1 "$(atleast1 "$(J d "$(R notification/index)" | grep -c 'Placement confirmed')")"
check "Coordinator told the student was placed" 1 "$(atleast1 "$(J c "$(R notification/index)" | grep -c 'Student placed')")"
check "Approving twice is refused" confirmed "$(approve $APPD2 > /dev/null; pls $UD)"
check "Approve B" 302 "$(approve $APPB)"
check "Approve C" 302 "$(approve $APPC)"
check "B and C confirmed" "confirmed|confirmed" "$(pls $UB)|$(pls $UC)"
check "Confirmed placements appear on the coordinator's list" 1 "$(atleast1 "$(J c "$(R coordinator/placed)" | grep -c "SMK8B/$N")")"
check "Placed student sees the already-placed message" 1 "$(atleast1 "$(J b "$(R vacancy/view)&id=$VD" | grep -c 'already have a placement')")"

echo "--- manual placement of a waiting applicant ---"
check "Reject E without a reason" 302 "$(post m application/decide -d "id=$APPE&action=reject" --data-urlencode "note=")"
check "E is still waiting" applied "$(appq status $UE $VD)"
check "Ministry places E by hand" 302 "$(post m application/decide -d "id=$APPE&action=place" --data-urlencode "supervisor_name=Pat Manager")"
check "E's placement is confirmed immediately" confirmed "$(pls $UE)"
check "Delta is filled" "filled|1" "$(vq status $VD)|$(vq slots_filled $VD)"
check "F became unsuccessful (vacancy filled)" unsuccessful "$(appq status $UF $VD)"
check "F was told the slots are filled" 1 "$(atleast1 "$(J f "$(R notification/index)" | grep -c 'filled')")"
check "Final count: 4 confirmed placements, none pending" "4|0" "$(q "SELECT COUNT(*) FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.id IN ($VA,$VB,$VD) AND p.status='confirmed'")|$(q "SELECT COUNT(*) FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.id IN ($VA,$VB,$VD) AND p.status='pending'")"
check "Audit log recorded the decisions" 1 "$(atleast1 "$(q "SELECT COUNT(*) FROM audit_log WHERE action IN ('auto_place','approve_placement','decline_placement')")")"

echo "Result: $ok passed, $bad failed (test users: $UA..$UF, $MU, $OU, $AU, $CU)"
php tests/cleanup.php
rm -rf "$TMP"
