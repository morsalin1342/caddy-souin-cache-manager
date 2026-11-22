#!/bin/sh
# End-to-end test: WordPress + MariaDB + Caddy/Souin in Docker.
#
# Each case caches a page through Caddy, changes the post with WP-CLI and
# then asks the Souin API which keys are left.
#
#   composer install && npm ci && npm run build   # once, in the repo root
#   tests/e2e/run.sh            # start the stack, run, tear down
#   KEEP=1 tests/e2e/run.sh     # leave the stack running afterwards
set -u

cd "$(dirname "$0")"
PORT="${E2E_HTTP_PORT:-18080}"
ADMIN="http://127.0.0.1:${E2E_ADMIN_PORT:-12019}/souin-api/souin"
SITE="http://localhost:$PORT"
DC="docker compose"
FAILED=0

if [ ! -f ../../vendor/autoload.php ]; then
	echo "Run composer install in the repository root first." >&2
	exit 1
fi

cleanup() { [ "${KEEP:-0}" = 1 ] || $DC down -v >/dev/null 2>&1; }
trap cleanup EXIT

wp() { $DC run --rm -T cli wp "$@" 2>/dev/null; }
keys() { curl -s "$ADMIN/" | tr ',' '\n'; }
fetch() { for p in "$@"; do curl -s -o /dev/null "$SITE$p"; done; }
cached() { keys | grep -q -- "-$1{-VARY-}"; }

pass() { echo "  ok   $1"; }
fail() { echo "  FAIL $1"; FAILED=1; }
expect_gone() { if cached "$1"; then fail "$2: $1 still cached"; else pass "$2: $1 purged"; fi; }
expect_kept() { if cached "$1"; then pass "$2: $1 kept"; else fail "$2: $1 was purged"; fi; }

new_post() { wp post create --post_status=publish --post_title="$1" --post_name="$1" --porcelain; }

echo "Starting the stack..."
$DC up -d --wait db wordpress caddy >/dev/null 2>&1
until wp core is-installed --url="$SITE" 2>/dev/null || wp core install --url="$SITE" --title=E2E \
	--admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null; do
	sleep 2
done
wp rewrite structure '/%postname%/' >/dev/null
wp plugin activate caddy-souin-cache-manager >/dev/null
wp option update cscm_settings '{"api_url":"http://caddy:2019/souin-api/souin","auto_purge":"1"}' --format=json >/dev/null
echo "WordPress $(wp core version), $($DC exec -T caddy caddy version | cut -d' ' -f1)"

wp post create --post_type=page --post_status=publish --post_title=Unrelated --post_name=unrelated >/dev/null

check() { # check <case> <status-change command...>
	name="$1"; shift
	id=$(new_post "$name")
	fetch "/$name/" / /unrelated/ "/?s=$name"
	cached "/$name/" || { fail "$name: page was not cached"; return; }
	"$@" "$id"
	sleep 1
	expect_gone "/$name/" "$name"
	expect_gone "/" "$name"
	expect_kept "/unrelated/" "$name"
	expect_kept "/?s=$name" "$name"
}

to_draft()   { wp post update "$1" --post_status=draft >/dev/null; }
to_private() { wp post update "$1" --post_status=private >/dev/null; }
to_pending() { wp post update "$1" --post_status=pending >/dev/null; }
to_trash()   { wp post delete "$1" >/dev/null; }
delete_now() { wp post delete "$1" --force >/dev/null; }
edit()       { wp post update "$1" --post_content=changed >/dev/null; }

echo "Automatic purge:"
check update-published edit
check publish-to-draft to_draft
check publish-to-private to_private
check publish-to-pending to_pending
check trash-post to_trash
check delete-published delete_now

# Deleting a post that is already in the trash.
id=$(new_post from-trash); wp post delete "$id" >/dev/null
fetch /
wp post delete "$id" --force >/dev/null; sleep 1
expect_gone "/" "delete-from-trash"

# Changing the slug purges the old URL.
id=$(new_post old-slug); fetch /old-slug/
wp post update "$id" --post_name=new-slug >/dev/null; sleep 1
expect_gone "/old-slug/" "slug-change"

# Publishing a draft purges the home page.
id=$(wp post create --post_status=draft --post_title=later --porcelain); fetch /
wp post update "$id" --post_status=publish >/dev/null; sleep 1
expect_gone "/" "draft-to-publish"

# Turning automatic purge off.
wp option patch update cscm_settings auto_purge 0 >/dev/null
id=$(new_post no-auto); fetch /no-auto/ /
wp post update "$id" --post_status=draft >/dev/null; sleep 1
expect_kept "/no-auto/" "auto-purge-off"
wp option patch update cscm_settings auto_purge 1 >/dev/null

echo "Manual purge (WP-CLI):"
fetch /unrelated/ "/?s=a.b" "/?s=q" "/?s=qq" /
key=$(keys | tr -d '[]"' | grep -- '-/?s=q{-VARY-}')
wp souin purge-key "$key" >/dev/null
expect_gone "/?s=q" "purge-key with ? and {"
expect_kept "/?s=qq" "purge-key"
expect_kept "/?s=a.b" "purge-key"
expect_kept "/unrelated/" "purge-key"
wp souin purge-regex '.*\/unrelated\/.*' >/dev/null
expect_gone "/unrelated/" "purge-regex with \/"
expect_kept "/" "purge-regex"
fetch /unrelated/
wp souin purge-regex '/unrelated/' >/dev/null
expect_gone "/unrelated/" "purge-regex starting with /"
if wp souin purge-regex '[a-z]+' >/dev/null 2>&1; then fail "purge-regex rejects [a-z]"; else pass "purge-regex rejects [a-z]"; fi

echo "REST API:"
wp config set WP_ENVIRONMENT_TYPE local >/dev/null
app=$(wp user application-password create admin e2e --porcelain)
rest() { curl -s -u "admin:$app" -H 'Content-Type: application/json' "$@"; }
code=$(rest -o /dev/null -w '%{http_code}' -X POST -d '{"type":"bogus"}' "$SITE/wp-json/cscm/v1/purge")
if [ "$code" = 400 ]; then pass "unknown purge type gives 400"; else fail "unknown purge type gave $code"; fi
secret='p@ss <zebra> %20 "q" '
rest -o /dev/null -X POST -d "{\"api_url\":\"http://caddy:2019/souin-api/souin\",\"redis_password\":\"p@ss <zebra> %20 \\\"q\\\" \"}" "$SITE/wp-json/cscm/v1/settings"
stored=$(wp eval 'echo get_option("cscm_settings")["redis_password"];')
if [ "$stored" = "$secret" ]; then pass "password stored unchanged"; else fail "password stored as [$stored]"; fi
if rest "$SITE/wp-json/cscm/v1/settings" | grep -q 'zebra'; then fail "settings expose the password"; else pass "settings hide the password"; fi
rest -o /dev/null -X POST -d '{"api_url":"http://caddy:2019/souin-api/souin","redis_password":"__cscm_saved_password__"}' "$SITE/wp-json/cscm/v1/settings"
stored=$(wp eval 'echo get_option("cscm_settings")["redis_password"];')
if [ "$stored" = "$secret" ]; then pass "placeholder keeps the password"; else fail "placeholder changed it to [$stored]"; fi

echo "WP-CLI status:"
if wp souin status | grep -q 'OK'; then pass "status reports OK"; else fail "status reports OK"; fi
wp option patch update cscm_settings api_url http://caddy:1/nowhere >/dev/null
if wp souin status >/dev/null 2>&1; then fail "status fails on a bad URL"; else pass "status fails on a bad URL"; fi
wp option patch update cscm_settings api_url http://caddy:2019/souin-api/souin >/dev/null

if [ "$FAILED" = 0 ]; then echo "All end-to-end checks passed."; else echo "Some end-to-end checks failed."; exit 1; fi
