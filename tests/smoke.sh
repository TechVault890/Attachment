#!/usr/bin/env bash
# Smoke test: bash tests/smoke.sh [base_url]   (creates a throwaway student 'smoke*')
BASE=${1:-http://127.0.0.1:8000}
JAR=$(mktemp); N=$RANDOM; U="smoke$N"; PW='Smoke@12345'
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

check "Home page loads"                    200 "$(code $BASE/index.php)"
check "Login page loads"                   200 "$(code "$BASE/index.php?r=auth/login")"
check "Register page loads"                200 "$(code "$BASE/index.php?r=auth/register")"
check "Unknown route returns 404"          404 "$(code "$BASE/index.php?r=nope/nothing")"
check "Path-traversal route returns 404"   404 "$(code "$BASE/index.php?r=../app/config")"
check "Dashboard needs login (redirect)"   302 "$(code "$BASE/index.php?r=dashboard/student")"
check "POST without CSRF token rejected"   419 "$(code -d 'identity=x&password=y' "$BASE/index.php?r=auth/login")"

T=$(curl -s -c $JAR -b $JAR "$BASE/index.php?r=auth/register" | token)
check "Register new student"               302 "$(code -c $JAR -b $JAR -X POST "$BASE/index.php?r=auth/register" \
  --data-urlencode "_csrf=$T" --data-urlencode "full_name=Smoke Tester" --data-urlencode "username=$U" \
  --data-urlencode "email=$U@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" \
  -d "institution_id=1&reg_no=SMK/$N&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=22&period_months=3")"

T=$(curl -s -c $JAR -b $JAR "$BASE/index.php?r=auth/login" | token)
check "Login with wrong password stays on page" 200 "$(code -c $JAR -b $JAR -X POST "$BASE/index.php?r=auth/login" --data-urlencode "_csrf=$T" -d "identity=$U&password=wrongpass")"
T=$(curl -s -c $JAR -b $JAR "$BASE/index.php?r=auth/login" | token)
check "Login with correct password"        302 "$(code -c $JAR -b $JAR -X POST "$BASE/index.php?r=auth/login" --data-urlencode "_csrf=$T" -d "identity=$U&password=$PW")"
PAGE=$(curl -s -c $JAR -b $JAR "$BASE/index.php?r=dashboard/student")
check "Student dashboard shows Pending"    1 "$(echo "$PAGE" | grep -c 'badge-pending')"
check "Student blocked from admin page"    403 "$(code -c $JAR -b $JAR "$BASE/index.php?r=dashboard/admin")"
T=$(echo "$PAGE" | token)
check "Logout"                             302 "$(code -c $JAR -b $JAR -X POST "$BASE/index.php?r=auth/logout" --data-urlencode "_csrf=$T")"
check "Dashboard blocked after logout"     302 "$(code -c $JAR -b $JAR "$BASE/index.php?r=dashboard/student")"

echo "Result: $ok passed, $bad failed (test user: $U)"
rm -f $JAR
