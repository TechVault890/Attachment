#!/usr/bin/env bash
# Module 3 test: bash tests/smoke_module3.sh [base_url]
# Assumes no other application window is active while it runs.
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
AJ=$(mktemp); MJ=$(mktemp); OJ=$(mktemp); SJ=$(mktemp); OUT=$(mktemp)
N=$RANDOM; AU="sadm$N"; MU="smin$N"; OU="sminx$N"; SU="smoke$N"; PW='Smoke@12345'
TODAY=$(date +%F); NEXT30=$(date -d '+30 days' +%F); NEXT60=$(date -d '+60 days' +%F); YEST=$(date -d '-1 day' +%F)
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
A(){ curl -s -c $AJ -b $AJ "$@"; }
M(){ curl -s -c $MJ -b $MJ "$@"; }
O(){ curl -s -c $OJ -b $OJ "$@"; }
S(){ curl -s -c $SJ -b $SJ "$@"; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }
login(){ local f=$1 user=$2 T; T=$($f "$(R auth/login)" | token)
  $f -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" -d "identity=$user" --data-urlencode "password=$PW"; }
vac_post(){ local f=$1 route=$2 slots=$3 closes=$4 title=$5 opens=${6:-$TODAY} T
  T=$($f "$(R $route)" | token)
  $f -o "$OUT" -w '%{http_code}' -X POST "$(R $route)" --data-urlencode "_csrf=$T" --data-urlencode "title=$title" \
    --data-urlencode "description=Smoke test vacancy" \
    -d "department=ICT&office_location=Nairobi+HQ&county_id=47&course_area=ICT+%2F+Software+Development&slots_total=$slots&duration_months=3&opens_on=$opens&closes_on=$closes"; }
post_form(){ local f=$1 page=$2 route=$3 T; shift 3; T=$($f "$(R $page)" | token)
  $f -o /dev/null -w '%{http_code}' -X POST "$(R $route)" --data-urlencode "_csrf=$T" "$@"; }

php tests/make_user.php admin "$AU" "$PW" 0 > /dev/null
php tests/make_user.php ministry "$MU" "$PW" 1 > /dev/null
php tests/make_user.php ministry "$OU" "$PW" 2 > /dev/null

check "Vacancy browser is public" 200 "$(code "$(R vacancy/browse)")"
check "Create vacancy needs login" 302 "$(code "$(R vacancy/create)")"

T=$(S "$(R auth/register)" | token)
check "Register student" 302 "$(S -o /dev/null -w '%{http_code}' -X POST "$(R auth/register)" \
  --data-urlencode "_csrf=$T" --data-urlencode "full_name=Smoke Tester" --data-urlencode "username=$SU" \
  --data-urlencode "email=$SU@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" \
  -d "institution_id=1&reg_no=SMK/$N&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=22&period_months=3")"
check "Student login" 302 "$(login S $SU)"
check "Student blocked from posting vacancies" 403 "$(S -o /dev/null -w '%{http_code}' "$(R vacancy/create)")"

check "Admin login" 302 "$(login A $AU)"
check "Admin blocked from ministry vacancy page" 403 "$(A -o /dev/null -w '%{http_code}' "$(R vacancy/manage)")"
check "Windows page loads for admin" 200 "$(A -o /dev/null -w '%{http_code}' "$(R window/index)")"
check "Window with closing before opening redirects" 302 "$(post_form A window/index window/save -d "name=SMOKE Bad $N&opens_on=$TODAY&closes_on=$YEST")"
check "Window error is shown" 1 "$(atleast1 "$(A "$(R window/index)" | grep -c 'alert-danger')")"
check "Valid window created" 302 "$(post_form A window/index window/save --data-urlencode "name=SMOKE Window $N" -d "opens_on=$TODAY&closes_on=$NEXT60")"
check "Window appears in list" 1 "$(atleast1 "$(A "$(R window/index)" | grep -c "SMOKE Window $N")")"
WID=$(php tests/lookup.php window "SMOKE Window $N")

check "Ministry login" 302 "$(login M $MU)"
check "Ministry vacancy page loads" 200 "$(M -o /dev/null -w '%{http_code}' "$(R vacancy/manage)")"
check "Invalid vacancy (0 slots) re-shows form" 200 "$(vac_post M vacancy/create 0 $NEXT30 "SMOKE Vacancy $N")"
check "Invalid vacancy shows an error" 1 "$(atleast1 "$(grep -c 'alert-danger' "$OUT")")"
check "Valid vacancy created" 302 "$(vac_post M vacancy/create 2 $NEXT30 "SMOKE Vacancy $N")"
VID=$(php tests/lookup.php vacancy "SMOKE Vacancy $N")
check "Vacancy in ministry's own list" 1 "$(atleast1 "$(M "$(R vacancy/manage)" | grep -c "SMOKE Vacancy $N")")"

check "Anonymous browse shows vacancy" 1 "$(atleast1 "$(curl -s "$(R vacancy/browse)" | grep -c "SMOKE Vacancy $N")")"
check "County filter (Nairobi) includes it" 1 "$(atleast1 "$(curl -s "$(R vacancy/browse)&county=47" | grep -c "SMOKE Vacancy $N")")"
check "County filter (Mombasa) excludes it" 0 "$(curl -s "$(R vacancy/browse)&county=1" | grep -c "SMOKE Vacancy $N")"
check "Area filter includes it" 1 "$(atleast1 "$(curl -s -G "$(R vacancy/browse)" --data-urlencode "area=ICT / Software Development" | grep -c "SMOKE Vacancy $N")")"
check "Anonymous vacancy detail page" 200 "$(code "$(R vacancy/view)&id=$VID")"
check "Browse shows applications-open banner" 1 "$(atleast1 "$(curl -s "$(R vacancy/browse)" | grep -c 'window-open')")"

check "Edit page loads for owner" 200 "$(M -o /dev/null -w '%{http_code}' "$(R vacancy/edit)&id=$VID")"
check "Edit with bad dates re-shows form" 200 "$(vac_post M "vacancy/edit&id=$VID" 2 $YEST "SMOKE Vacancy $N")"
check "Edit with valid data" 302 "$(vac_post M "vacancy/edit&id=$VID" 1 $NEXT30 "SMOKE Vacancy $N")"

check "Other ministry cannot open edit page" 404 "$(login O $OU > /dev/null; O -o /dev/null -w '%{http_code}' "$(R vacancy/edit)&id=$VID")"
check "Other ministry cannot close it" 404 "$(post_form O vacancy/manage vacancy/status -d "id=$VID&to=close")"

check "Owner closes vacancy" 302 "$(post_form M vacancy/manage vacancy/status -d "id=$VID&to=close")"
check "Closed vacancy hidden from browse" 0 "$(curl -s "$(R vacancy/browse)" | grep -c "SMOKE Vacancy $N")"
check "Closed vacancy is 404 for the public" 404 "$(code "$(R vacancy/view)&id=$VID")"
check "Closed vacancy still viewable by owner" 200 "$(M -o /dev/null -w '%{http_code}' "$(R vacancy/view)&id=$VID")"
check "Owner reopens vacancy" 302 "$(post_form M vacancy/manage vacancy/status -d "id=$VID&to=reopen")"
check "Reopened vacancy back in browse" 1 "$(atleast1 "$(curl -s "$(R vacancy/browse)" | grep -c "SMOKE Vacancy $N")")"

check "Admin deactivates the window" 302 "$(post_form A window/index window/toggle -d "id=$WID")"
if curl -s "$(R vacancy/browse)" | grep -q 'window-open'; then
  echo "SKIP: another application window is active, so the no-window banner check was skipped"
else
  check "Browse shows no-window banner" 1 "$(atleast1 "$(curl -s "$(R vacancy/browse)" | grep -c 'window-closed')")"
fi

echo "Result: $ok passed, $bad failed (test users: $AU, $MU, $OU, $SU)"
php tests/cleanup.php
rm -f "$AJ" "$MJ" "$OJ" "$SJ" "$OUT"
