#!/usr/bin/env bash
# Module 7 test (reports, CSV export, audit log): bash tests/smoke_module7.sh [base_url]
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
TMP=$(mktemp -d)
N=$RANDOM; PW='Smoke@12345'
AU="sadm$N"; MU="smin$N"; CU="scoord$N"; US="smoke${N}s"
TODAY=$(date +%F); NEXT30=$(date -d '+30 days' +%F); NEXT60=$(date -d '+60 days' +%F)
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
J(){ local n=$1; shift; curl -s -c "$TMP/$n.jar" -b "$TMP/$n.jar" "$@"; }
code(){ local n=$1; shift; J "$n" -o /dev/null -w '%{http_code}' "$@"; }
post(){ local n=$1 route=$2 T; shift 2; T=$(J "$n" "$(R vacancy/browse)" | token); [ -z "$T" ] && T=$(J "$n" "$(R auth/register)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R "$route")" --data-urlencode "_csrf=$T" "$@"; }
login(){ local n=$1 user=$2 T; T=$(J "$n" "$(R auth/login)" | token)
  J "$n" -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" --data-urlencode "identity=$user" --data-urlencode "password=$PW"; }
q(){ php tests/dbq.php "$1"; }
csvcell(){ php tests/csvcell.php "$TMP/$1.csv" "$2" "$3"; }
export_csv(){ J adm -o "$TMP/$1.csv" -D "$TMP/$1.hdr" -w '%{http_code}' "$(R report/export)&type=$1$2"; }

php tests/make_user.php admin "$AU" "$PW" 0 > /dev/null
php tests/make_user.php ministry "$MU" "$PW" 1 > /dev/null
php tests/make_user.php coordinator "$CU" "$PW" 1 > /dev/null
check "CSV formula protection (unit test)" "all csv_safe cases OK" "$(php tests/csv_test.php)"

echo "--- setup: one placement ---"
check "Admin login" 302 "$(login adm $AU)"
check "Ministry login" 302 "$(login m $MU)"
check "Coordinator login" 302 "$(login c $CU)"
check "Create application window" 302 "$(post adm window/save --data-urlencode "name=SMOKE Window $N" -d "opens_on=$TODAY&closes_on=$NEXT60")"
check "Post vacancy" 302 "$(post m vacancy/create --data-urlencode "title=SMOKE Delta $N" -d "department=ICT&office_location=Test+Office&county_id=47&course_area=ICT+%2F+Software+Development&slots_total=2&duration_months=3&opens_on=$TODAY&closes_on=$NEXT30")"
VID=$(php tests/lookup.php vacancy "SMOKE Delta $N")
T=$(J s "$(R auth/register)" | token)
check "Register student" 302 "$(J s -o /dev/null -w '%{http_code}' -X POST "$(R auth/register)" --data-urlencode "_csrf=$T" --data-urlencode "full_name=Smoke Tester S" \
  --data-urlencode "username=$US" --data-urlencode "email=$US@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" \
  --data-urlencode "reg_no=SMK7/$N" -d "institution_id=1&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=47&period_months=3")"
check "Student login" 302 "$(login s $US)"
php tests/verify_student.php "$US"
check "Student applies" 302 "$(post s application/apply -d "vacancy_id=$VID")"
APP=$(q "SELECT a.id FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.username = '$US' AND a.vacancy_id = $VID")
check "Ministry places the student" 302 "$(post m application/decide -d "id=$APP&action=place" --data-urlencode "supervisor_name=Sam Supervisor" -d "supervisor_phone=0722000000")"

echo "--- access control ---"
for who in s c m; do
  check "$who blocked from reports page" 403 "$(code $who "$(R report/index)")"
  check "$who blocked from CSV export" 403 "$(code $who "$(R report/export)&type=county")"
  check "$who blocked from audit log" 403 "$(code $who "$(R audit/index)")"
done
check "Anonymous redirected from reports" 302 "$(curl -s -o /dev/null -w '%{http_code}' "$(R report/index)")"
check "Anonymous redirected from export" 302 "$(curl -s -o /dev/null -w '%{http_code}' "$(R report/export)&type=county")"

echo "--- reports page ---"
check "Admin opens reports" 200 "$(code adm "$(R report/index)")"
RP=$(J adm "$(R report/index)")
check "Page has the county section" 1 "$(atleast1 "$(echo "$RP" | grep -c 'Placements per county')")"
check "Page has the slots section" 1 "$(atleast1 "$(echo "$RP" | grep -c 'Open vs filled slots')")"
check "Valid date filter shows no error" 0 "$(J adm "$(R report/index)&from=2000-01-01&to=2099-12-31" | grep -c 'alert-danger')"
check "Invalid date shows an error" 1 "$(atleast1 "$(J adm "$(R report/index)&from=notadate" | grep -c 'alert-danger')")"
check "Reversed range shows an error" 1 "$(atleast1 "$(J adm "$(R report/index)&from=2099-01-01&to=2000-01-01" | grep -c 'alert-danger')")"

echo "--- CSV exports ---"
check "County CSV downloads" 200 "$(export_csv county)"
check "CSV content type" 1 "$(atleast1 "$(grep -ci 'text/csv' "$TMP/county.hdr")")"
check "CSV has a UTF-8 BOM for Excel" efbbbf "$(head -c3 "$TMP/county.csv" | od -An -tx1 | tr -d ' \n')"
check "CSV attachment filename" 1 "$(atleast1 "$(grep -ci 'attachment; filename="county-' "$TMP/county.hdr")")"
check "Nairobi total matches the database" "$(q "SELECT COUNT(*) FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.county_id = 47")" "$(csvcell county 'Nairobi,' 2)"
check "Ministry CSV downloads" 200 "$(export_csv ministry)"
MIN1=$(q "SELECT name FROM ministries WHERE id = 1")
check "Ministry total matches the database" "$(q "SELECT COUNT(*) FROM placements p JOIN vacancies v ON v.id = p.vacancy_id WHERE v.ministry_id = 1")" "$(csvcell ministry "$MIN1," 2)"
check "Institution CSV downloads" 200 "$(export_csv institution)"
INS1=$(q "SELECT name FROM institutions WHERE id = 1")
check "Institution total matches the database" "$(q "SELECT COUNT(*) FROM placements p JOIN students s ON s.id = p.student_id WHERE s.institution_id = 1")" "$(csvcell institution "$INS1," 4)"
check "Slots CSV downloads" 200 "$(export_csv slots)"
check "Slots total matches the database" "$(q "SELECT COALESCE(SUM(slots_total),0) FROM vacancies WHERE ministry_id = 1")" "$(csvcell slots "$MIN1," 3)"
check "Placements CSV downloads" 200 "$(export_csv placements)"
check "Placements CSV lists the student" 1 "$(atleast1 "$(grep -c "SMK7/$N" "$TMP/placements.csv")")"
check "Placements CSV lists the supervisor" 1 "$(atleast1 "$(grep -c 'Sam Supervisor' "$TMP/placements.csv")")"
check "Date range excludes old placements" 0 "$(export_csv county '&from=2000-01-01&to=2000-01-02' > /dev/null; grep -c '^Nairobi,' "$TMP/county.csv")"
check "Unknown export type is 404" 404 "$(code adm "$(R report/export)&type=bogus")"
check "Invalid export dates are 400" 400 "$(code adm "$(R report/export)&type=county&from=nope")"

echo "--- audit log ---"
check "Exports were audited" 1 "$(atleast1 "$(q "SELECT COUNT(*) FROM audit_log WHERE action = 'export_report'")")"
check "Audit page loads" 200 "$(code adm "$(R audit/index)")"
check "Unfiltered audit shows the export" 1 "$(atleast1 "$(J adm "$(R audit/index)" | grep -c '<td>export_report</td>')")"
AF=$(J adm "$(R audit/index)&action=place_student")
check "Filter shows placement entries" 1 "$(atleast1 "$(echo "$AF" | grep -c '<td>place_student</td>')")"
check "Filter hides other actions" 0 "$(echo "$AF" | grep -c '<td>export_report</td>')"
check "Out-of-range page is clamped" 200 "$(code adm "$(R audit/index)&page=99999")"
check "Unknown action filter is ignored" 200 "$(code adm "$(R audit/index)&action=%27%3Bdrop")"
check "Invalid audit date shows an error" 1 "$(atleast1 "$(J adm "$(R audit/index)&from=nope" | grep -c 'alert-danger')")"

echo "Result: $ok passed, $bad failed (test users: $AU, $MU, $CU, $US)"
php tests/cleanup.php
rm -rf "$TMP"
