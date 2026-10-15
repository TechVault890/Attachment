#!/usr/bin/env bash
# Module 2 test: bash tests/smoke_module2.sh [base_url]
cd "$(dirname "$0")/.." || exit 1
BASE=${1:-http://127.0.0.1:8000}
SJ=$(mktemp); CJ=$(mktemp); XJ=$(mktemp); TMP=$(mktemp -d)
N=$RANDOM; SU="smoke$N"; CU="scoord$N"; XU="scoordx$N"; PW='Smoke@12345'
ok=0; bad=0
check(){ if [ "$2" = "$3" ]; then echo "PASS: $1"; ok=$((ok+1)); else echo "FAIL: $1 (expected $2, got $3)"; bad=$((bad+1)); fi; }
S(){ curl -s -c $SJ -b $SJ "$@"; }
C(){ curl -s -c $CJ -b $CJ "$@"; }
X(){ curl -s -c $XJ -b $XJ "$@"; }
token(){ grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
R(){ echo "$BASE/index.php?r=$1"; }
login(){ local f=$1 user=$2 T; T=$($f "$(R auth/login)" | token)
  $f -o /dev/null -w '%{http_code}' -X POST "$(R auth/login)" --data-urlencode "_csrf=$T" -d "identity=$user" --data-urlencode "password=$PW"; }
up(){ local type=$1 file=$2 T; T=$(S "$(R student/documents)" | token)
  S -o /dev/null -w '%{http_code}' -X POST "$(R student/upload)" -F "_csrf=$T" -F "doc_type=$type" -F "file=@$file"; }
atleast1(){ [ "$1" -ge 1 ] && echo 1 || echo 0; }

printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > $TMP/ok.pdf
printf 'MZ\x90\x00 this is not a pdf' > $TMP/fake.pdf
{ printf '%%PDF-1.4\n'; head -c 3000000 /dev/zero; } > $TMP/big.pdf

T=$(S "$(R auth/register)" | token)
check "Register student" 302 "$(S -o /dev/null -w '%{http_code}' -X POST "$(R auth/register)" \
  --data-urlencode "_csrf=$T" --data-urlencode "full_name=Smoke Tester" --data-urlencode "username=$SU" \
  --data-urlencode "email=$SU@example.com" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" \
  -d "institution_id=1&reg_no=SMK/$N&course=BBIT&area_of_interest=ICT+%2F+Software+Development&preferred_county_id=22&period_months=3")"
php tests/make_coord.php "$CU" "$PW" 1 > /dev/null
php tests/make_coord.php "$XU" "$PW" 2 > /dev/null

check "Student login" 302 "$(login S $SU)"
check "Profile page loads" 200 "$(S -o /dev/null -w '%{http_code}' "$(R student/profile)")"
T=$(S "$(R student/profile)" | token)
check "Profile update" 302 "$(S -o /dev/null -w '%{http_code}' -X POST "$(R student/profile)" --data-urlencode "_csrf=$T" \
  -d "phone=0712345678&course=BBIT&area_of_interest=Law&preferred_county_id=27&period_months=6")"

check "Fake PDF upload redirects" 302 "$(up cv $TMP/fake.pdf)"
check "Fake PDF rejected with error" 1 "$(S "$(R student/documents)" | grep -c 'alert-danger')"
check "3 MB file upload redirects" 302 "$(up cv $TMP/big.pdf)"
check "Oversize file rejected with error" 1 "$(S "$(R student/documents)" | grep -c 'alert-danger')"
for t in insurance recommendation_letter transcript cv; do check "Upload $t" 302 "$(up $t $TMP/ok.pdf)"; done
PAGE=$(S "$(R student/documents)")
check "Documents page shows 4 uploaded" 4 "$(echo "$PAGE" | grep -c 'doc-ok')"
check "No uploaded files inside public/" 0 "$(find public -type f \( -name '*.pdf' -o -name '*.doc*' \) | wc -l)"

DID=$(echo "$PAGE" | grep -o 'document%2Fdownload&amp;id=[0-9]*' | head -1 | grep -o '[0-9]*$')
check "Student downloads own document" 200 "$(S -o /dev/null -w '%{http_code}' "$(R document/download)&id=$DID")"
check "Unknown document id gives 404" 404 "$(S -o /dev/null -w '%{http_code}' "$(R document/download)&id=999999")"
check "Student blocked from coordinator queue" 403 "$(S -o /dev/null -w '%{http_code}' "$(R coordinator/queue)")"

check "Coordinator login" 302 "$(login C $CU)"
Q=$(C "$(R coordinator/queue)")
check "Queue lists the student" 1 "$(atleast1 "$(echo "$Q" | grep -c "SMK/$N")")"
SID=$(echo "$Q" | grep -o 'coordinator%2Freview&amp;id=[0-9]*' | head -1 | grep -o '[0-9]*$')
check "Coordinator downloads student document" 200 "$(C -o /dev/null -w '%{http_code}' "$(R document/download)&id=$DID")"

check "Other-institution coordinator login" 302 "$(login X $XU)"
check "Other-institution review blocked (404)" 404 "$(X -o /dev/null -w '%{http_code}' "$(R coordinator/review)&id=$SID")"
check "Other-institution download blocked (403)" 403 "$(X -o /dev/null -w '%{http_code}' "$(R document/download)&id=$DID")"

decide(){ local d=$1 reason=$2 T; T=$(C "$(R coordinator/review)&id=$SID" | token)
  C -o /dev/null -w '%{http_code}' -X POST "$(R coordinator/decide)" --data-urlencode "_csrf=$T" -d "id=$SID&decision=$d" --data-urlencode "reason=$reason"; }
check "Reject without reason redirects" 302 "$(decide reject '')"
check "Reject without reason shows error" 1 "$(C "$(R coordinator/review)&id=$SID" | grep -c 'alert-danger')"
check "Reject with reason" 302 "$(decide reject 'Transcript is not stamped')"
check "Student sees Rejected" 1 "$(S "$(R dashboard/student)" | grep -c 'badge-rejected')"
T=$(S "$(R student/profile)" | token)
check "Student resubmits profile" 302 "$(S -o /dev/null -w '%{http_code}' -X POST "$(R student/profile)" --data-urlencode "_csrf=$T" \
  -d "phone=0712345678&course=BBIT&area_of_interest=Law&preferred_county_id=27&period_months=6")"
check "Student back to Pending" 1 "$(S "$(R dashboard/student)" | grep -c 'badge-pending')"
check "Verify student" 302 "$(decide verify '')"
check "Student sees Verified" 1 "$(S "$(R dashboard/student)" | grep -c 'badge-verified')"
check "Upload blocked after verification" 302 "$(up cv $TMP/ok.pdf)"
check "Locked warning shown" 1 "$(atleast1 "$(S "$(R student/documents)" | grep -c 'alert-warning')")"
T=$(S "$(R dashboard/student)" | token)
check "Student cannot POST a decision" 403 "$(S -o /dev/null -w '%{http_code}' -X POST "$(R coordinator/decide)" --data-urlencode "_csrf=$T" -d "id=$SID&decision=verify")"

echo "Result: $ok passed, $bad failed (test users: $SU, $CU, $XU)"
php tests/cleanup.php
rm -rf "$SJ" "$CJ" "$XJ" "$TMP"
