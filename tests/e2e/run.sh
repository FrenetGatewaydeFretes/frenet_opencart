#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 3 ] || [ $# -gt 4 ]; then
	echo "Usage: $0 <opencart-version> <php-version> <frenet.ocmod.zip> [<previous.ocmod.zip>]" >&2
	exit 2
fi

OC_VERSION=$1
export PHP_VERSION=$2
PACKAGE=$(realpath "$3")
PREVIOUS_PACKAGE=$([ $# -eq 4 ] && realpath "$4" || true)
MODE=$([ -n "$PREVIOUS_PACKAGE" ] && echo upgrade || echo install)
DIR=$(cd "$(dirname "$0")" && pwd)
WORK="$DIR/.work"
ARTIFACTS="$WORK/artifacts/$OC_VERSION-$MODE"
PLAYWRIGHT_VERSION=$(sed -n 's/.*"@playwright\/test": "\([^"]*\)".*/\1/p' "$DIR/package.json")
export E2E_PORT=${E2E_PORT:-8080}
export OPENCART_ZIP="$WORK/opencart/opencart-$OC_VERSION.zip"
BASE_URL="http://localhost:$E2E_PORT/"

dc() {
	docker compose -f "$DIR/compose.yml" "$@"
}

sql() {
	dc exec -T db mariadb -uopencart -popencart opencart -N -e "$1"
}

collect() {
	local status=$?

	mkdir -p "$ARTIFACTS"
	dc logs --no-color > "$ARTIFACTS/compose.log" 2>&1 || true
	dc exec -T web sh -c 'cd "$(php -r "require \"/var/www/html/config.php\"; echo DIR_LOGS;")" && cat error.log frenet.log 2>/dev/null' > "$ARTIFACTS/opencart.log" 2>&1 || true
	cp "$WORK/log/requests.jsonl" "$ARTIFACTS/mock-requests.jsonl" 2>/dev/null || true
	[ -d "$DIR/test-results" ] && cp -r "$DIR/test-results" "$ARTIFACTS/" || true
	[ "${E2E_KEEP:-0}" = 1 ] || dc down -v --remove-orphans > /dev/null 2>&1 || true
	exit $status
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

mkdir -p "$WORK/opencart" "$WORK/tls" "$WORK/log"
rm -rf "$ARTIFACTS" "$DIR/test-results"

if [ ! -s "$OPENCART_ZIP" ]; then
	url="https://github.com/opencart/opencart/releases/download/$OC_VERSION/opencart-$OC_VERSION.zip"
	[ "$OC_VERSION" = 3.0.0.0 ] && url="https://github.com/opencart/opencart/releases/download/3.0.0.0/3.0.0.0-compiled.zip"
	curl -fsSL --retry 3 --retry-delay 5 -o "$OPENCART_ZIP.part" "$url"
	mv "$OPENCART_ZIP.part" "$OPENCART_ZIP"
fi

(
	cd "$WORK/tls"
	openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj '/CN=Frenet E2E CA' -addext 'basicConstraints=critical,CA:TRUE' -keyout ca.key -out ca.pem 2> /dev/null
	openssl req -newkey rsa:2048 -nodes -subj '/CN=api.frenet.com.br' -keyout server.key -out server.csr 2> /dev/null
	printf 'subjectAltName=DNS:api.frenet.com.br\nbasicConstraints=CA:FALSE\nextendedKeyUsage=serverAuth\n' > server.ext
	openssl x509 -req -in server.csr -CA ca.pem -CAkey ca.key -CAcreateserial -days 2 -extfile server.ext -out server.pem 2> /dev/null
	chmod 644 server.key
)

: > "$WORK/log/requests.jsonl"
chmod 666 "$WORK/log/requests.jsonl"

dc down -v --remove-orphans > /dev/null 2>&1 || true
trap collect EXIT
dc up -d --build --quiet-pull

echo "Installing OpenCart $OC_VERSION on PHP $PHP_VERSION"
dc exec -T web update-ca-certificates > /dev/null
dc exec -T web sh -euc '
	rm -rf /tmp/oc && mkdir /tmp/oc && cd /tmp/oc && unzip -q /opencart.zip
	upload=$(dirname "$(find . -maxdepth 4 -path "*/upload/index.php" | head -1)")
	cp -a "$upload/." /var/www/html/
	cd /var/www/html && cp config-dist.php config.php && cp admin/config-dist.php admin/config.php
	chown -R www-data:www-data /var/www/html
	rm -rf /tmp/oc
'
dc exec -T -u www-data web php /var/www/html/install/cli_install.php install \
	--db_driver mysqli --db_hostname db --db_username opencart --db_password opencart --db_database opencart \
	--db_port 3306 --db_prefix oc_ --username admin --password admin --email e2e@example.com --http_server "$BASE_URL" | tail -1
dc exec -T web rm -rf /var/www/html/install
dc exec -T web php -r '$h = curl_init("https://api.frenet.com.br/mock/status/200"); curl_setopt($h, CURLOPT_RETURNTRANSFER, true); curl_exec($h); exit(curl_getinfo($h, CURLINFO_RESPONSE_CODE) === 200 ? 0 : 1);' \
	|| fail "the store cannot reach the Frenet mock over HTTPS"

echo "Running Playwright $PLAYWRIGHT_VERSION ($MODE)"
cd "$DIR"
if command -v npx > /dev/null && [ -d node_modules/@playwright/test ]; then
	BASE_URL="$BASE_URL" FRENET_PACKAGE="$PACKAGE" FRENET_PREVIOUS_PACKAGE="$PREVIOUS_PACKAGE" npx playwright test
else
	docker run --rm --network host --ipc host -u "$(id -u):$(id -g)" -e HOME=/tmp -e BASE_URL="$BASE_URL" \
		-e FRENET_PACKAGE=/package/frenet.ocmod.zip -v "$PACKAGE":/package/frenet.ocmod.zip:ro \
		${PREVIOUS_PACKAGE:+-e FRENET_PREVIOUS_PACKAGE=/package/previous.ocmod.zip -v "$PREVIOUS_PACKAGE":/package/previous.ocmod.zip:ro} \
		-v "$DIR":/e2e -w /e2e "mcr.microsoft.com/playwright:v$PLAYWRIGHT_VERSION-noble" npx playwright test
fi

echo "Checking the order, the mock and the logs"
shipping_code=$(sql "SELECT shipping_code FROM oc_order WHERE order_id = 1 AND order_status_id > 0")
[[ $shipping_code == frenet.* ]] || fail "order 1 has shipping code '$shipping_code', expected frenet.*"
legacy=$(sql "SELECT COUNT(*) FROM oc_setting WHERE \`key\` IN ('shipping_frenet_contrato_codigo', 'shipping_frenet_contrato_senha')")
[ "$legacy" = 0 ] || fail "the key and password of the previous version are still saved"
grep -q '"token":"e2e-token"' "$WORK/log/requests.jsonl" || fail "the mock received no quote with the saved token"
grep -q '"PlatformName":"OpenCart3"' "$WORK/log/requests.jsonl" || fail "the quote did not identify the platform as OpenCart3"
php_errors=$(dc exec -T web sh -c 'cat "$(php -r "require \"/var/www/html/config.php\"; echo DIR_LOGS;")error.log" 2>/dev/null' | grep -ci 'frenet' || true)
[ "$php_errors" = 0 ] || fail "error.log has $php_errors line(s) about Frenet"

echo "OK: OpenCart $OC_VERSION (PHP $PHP_VERSION) $MODE: quoted and placed order 1 with $shipping_code"
