#!/usr/bin/env bash
# Module 6 test (coordinator placed view, assessments, admin users/organisations): bash tests/smoke_module6.sh [base_url]
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
TMP=$(mktemp -d)
N=$RANDOM; PW='Smoke@12345'
AU="sadm$N"; MU="smin$N"; CU="scoord$N"; XU="scoordx$N"; NU="sminui$N"; US="smoke${N}s"
TODAY=$(date +%F); NEXT10=$(date -d '+10 days' +%F); NEXT30=$(date -d '+30 days' +%F); NEXT60=$(date -d '+60 days' +%F)
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
J(){ local n=$1; shift; curl -s -c "$TMP/$n.jar" -b "$TMP/$n.jar" "$@"; }
code(){ local n=$1; shift; J "$n" -o /dev/null -w '%{http_code}' "$@"; }
post(){ local n=$1 route=$2 T; shift 2; T=$(J "$n" "$(R vacancy/browse)" | token); [ -z "$T" ] && T=$(J "$n" "$(R auth/register)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R "$route")" --data-urlencode "_csrf=$T" "$@"; }
postbody(){ local n=$1 route=$2 T; shift 2; T=$(J "$n" "$(R vacancy/browse)" | token); [ -z "$T" ] && T=$(J "$n" "$(R auth/register)" | token)
  J "$n" -X POST "$(R "$route")" --data-urlencode "_csrf=$T" "$@"; }
login(){ local n=$1 user=$2 T; T=$(J "$n" "$(R auth/login)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" --data-urlencode "identity=$user" --data-urlencode "password=$PW"; }
q(){ php tests/dbq.php "$1"; }

php tests/make_user.php admin "$AU" "$PW" 0 > /dev/null
php tests/make_user.php ministry "$MU" "$PW" 1 > /dev/null
php tests/make_user.php coordinator "$XU" "$PW" 2 > /dev/null

echo "--- admin: users ---"
check "Admin login" 302 "$(login adm $AU)"
check "Users page loads" 200 "$(code adm "$(R user/index)")"
check "Create-account form loads" 200 "$(code adm "$(R user/create)")"
check "Create coordinator account" 302 "$(post adm user/create --data-urlencode "full_name=Smoke Coordinator" --data-urlencode "username=$CU" --data-urlencode "email=$CU@example.com" --data-urlencode "password=$PW" -d "role=coordinator&institution_id=1&ministry_id=")"
check "Duplicate username rejected" 1 "$(atleast1 "$(postbody adm user/create --data-urlencode "full_name=Smoke Again" --data-urlencode "username=$CU" --data-urlencode "email=other$N@example.com" --data-urlencode "password=$PW" -d "role=coordinator&institution_id=1&ministry_id=" | grep -c 'already in use')")"
check "Short password rejected" 1 "$(atleast1 "$(postbody adm user/create --data-urlencode "full_name=Smoke Short" --data-urlencode "username=smoke${N}x" --data-urlencode "email=short$N@example.com" -d "password=abc&role=admin&institution_id=&ministry_id=" | grep -c 'at least 8')")"
check "Coordinator without institution rejected" 1 "$(atleast1 "$(postbody adm user/create --data-urlencode "full_name=Smoke NoOrg" --data-urlencode "username=smoke${N}y" --data-urlencode "email=noorg$N@example.com" --data-urlencode "password=$PW" -d "role=coordinator&institution_id=&ministry_id=" | grep -c 'Choose the institution')")"
check "Create ministry officer account" 302 "$(post adm user/create --data-urlencode "full_name=Smoke Officer" --data-urlencode "username=$NU" --data-urlencode "email=$NU@example.com" --data-urlencode "password=$PW" -d "role=ministry&institution_id=&ministry_id=1")"
check "New coordinator can log in" 302 "$(login c $CU)"
check "New ministry officer can log in" 302 "$(login n $NU)"
check "Coordinator blocked from Users page" 403 "$(code c "$(R user/index)")"

CID=$(q "SELECT id FROM users WHERE username='$CU'"); AID=$(q "SELECT id FROM users WHERE username='$AU'")
check "Deactivate the coordinator" 302 "$(post adm user/toggle -d "id=$CID&back=staff")"
check "Account is inactive in the database" 0 "$(q "SELECT is_active FROM users WHERE id=$CID")"
check "Deactivated user cannot log in" 200 "$(login c2 $CU)"
check "Deactivated user's open session is cut off" 302 "$(code c "$(R dashboard/index)")"
check "Reactivate the coordinator" 302 "$(post adm user/toggle -d "id=$CID&back=staff")"
check "Reactivated user can log in" 302 "$(login c $CU)"
post adm user/toggle -d "id=$AID&back=staff" > /dev/null
check "Admin cannot deactivate themselves" 1 "$(q "SELECT is_active FROM users WHERE id=$AID")"

echo "--- admin: organisations ---"
check "Organisations page loads" 200 "$(code adm "$(R org/index)")"
check "Add institution" 302 "$(post adm org/add -d "type=institution&code=SMK" --data-urlencode "name=SMOKE Uni $N")"
check "New institution appears on registration form" 1 "$(atleast1 "$(curl -s "$(R auth/register)" | grep -c "SMOKE Uni $N")")"
post adm org/add -d "type=institution&code=SMK" --data-urlencode "name=SMOKE Uni $N" > /dev/null
check "Duplicate institution shows an error" 1 "$(atleast1 "$(J adm "$(R org/index)" | grep -c 'alert-danger')")"
check "Organisations page warns about missing coordinator" 1 "$(atleast1 "$(J adm "$(R org/index)" | grep -c 'No coordinator')")"
check "Add Ministry" 302 "$(post adm org/add -d "type=ministry&code=SMM" --data-urlencode "name=SMOKE Ministry $N")"
check "Ministry saved" 1 "$(q "SELECT COUNT(*) FROM ministries WHERE name='SMOKE Ministry $N'")"
check "Coordinator blocked from Organisations page" 403 "$(code c "$(R org/index)")"

echo "--- setup: a placed student ---"
check "Ministry login" 302 "$(login m $MU)"
check "Other-institution coordinator login" 302 "$(login x $XU)"
check "Create application window" 302 "$(post adm window/save --data-urlencode "name=SMOKE Window $N" -d "opens_on=$TODAY&closes_on=$NEXT60")"
check "Post vacancy Gamma (Nairobi)" 302 "$(post m vacancy/create --data-urlencode "title=SMOKE Gamma $N" -d "department=ICT&office_location=Test+Office&county_id=47&course_area=ICT+%2F+Software+Development&slots_total=2&duration_months=3&opens_on=$TODAY&closes_on=$NEXT30")"
VG=$(php tests/lookup.php vacancy "SMOKE Gamma $N")
check "Register student" 302 "$(post s auth/register --data-urlencode "full_name=Smoke Tester S" --data-urlencode "username=$US" --data-urlencode "email=$US@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" --data-urlencode "reg_no=SMK6/$N" -d "institution_id=1&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=47&period_months=3")"
check "Student login" 302 "$(login s $US)"
php tests/verify_student.php "$US"
check "Student applies" 302 "$(post s application/apply -d "vacancy_id=$VG")"
APP=$(q "SELECT a.id FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$US' AND a.vacancy_id = $VG")
check "Ministry places the student" 302 "$(post m application/decide -d "id=$APP&action=place" --data-urlencode "supervisor_name=Sam Supervisor" -d "supervisor_phone=0722000000")"
PID=$(q "SELECT p.id FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.title = 'SMOKE Gamma $N'")

echo "--- coordinator: placed students ---"
check "Coordinator opens Placed students" 200 "$(code c "$(R coordinator/placed)")"
check "Placed list shows the student and supervisor" 1 "$(atleast1 "$(J c "$(R coordinator/placed)" | grep -c "Sam Supervisor")")"
check "Other institution does not see the student" 0 "$(J x "$(R coordinator/placed)" | grep -c "SMK6/$N")"
check "County filter (Nairobi) includes the student" 1 "$(atleast1 "$(J c "$(R coordinator/placed)&county=47" | grep -c "SMK6/$N")")"
check "County filter (Mombasa) excludes the student" 0 "$(J c "$(R coordinator/placed)&county=1" | grep -c "SMK6/$N")"
check "Student blocked from Placed students" 403 "$(code s "$(R coordinator/placed)")"

echo "--- coordinator: assessments ---"
check "Coordinator opens the assessment form" 200 "$(code c "$(R coordinator/assess)&id=$PID")"
check "Other institution gets 404 on the assessment" 404 "$(code x "$(R coordinator/assess)&id=$PID")"
check "Student blocked from the assessment form" 403 "$(code s "$(R coordinator/assess)&id=$PID")"
check "Score above 100 rejected" 1 "$(atleast1 "$(postbody c "coordinator/assess&id=$PID" -d "assessed_on=$TODAY&score=150" --data-urlencode "comments=x" | grep -c 'alert-danger')")"
check "Future date rejected" 1 "$(atleast1 "$(postbody c "coordinator/assess&id=$PID" -d "assessed_on=$NEXT10&score=80" --data-urlencode "comments=x" | grep -c 'in the future')")"
check "No assessment saved by invalid attempts" 0 "$(q "SELECT COUNT(*) FROM assessments WHERE placement_id=$PID")"
check "Other institution cannot record an assessment" 404 "$(post x "coordinator/assess&id=$PID" -d "assessed_on=$TODAY&score=70")"
check "Valid assessment saved" 302 "$(post c "coordinator/assess&id=$PID" -d "assessed_on=$TODAY&score=85" --data-urlencode "comments=Good punctuality")"
check "One assessment stored" 1 "$(q "SELECT COUNT(*) FROM assessments WHERE placement_id=$PID")"
check "History shows the comment" 1 "$(atleast1 "$(J c "$(R coordinator/assess)&id=$PID" | grep -c 'Good punctuality')")"
check "Placed list shows the last score" 1 "$(atleast1 "$(J c "$(R coordinator/placed)" | grep -c '85/100')")"
check "Student was notified" 1 "$(atleast1 "$(J s "$(R notification/index)" | grep -ci 'assessment')")"

echo "Result: $ok passed, $bad failed (test users: $AU, $MU, $CU, $XU, $NU, $US)"
php tests/cleanup.php
rm -rf "$TMP"
