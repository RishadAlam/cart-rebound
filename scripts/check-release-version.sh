#!/usr/bin/env bash

set -euo pipefail

TAG_NAME="${1:-}"

if [[ -z "${TAG_NAME}" ]]; then
	echo "Usage: $0 <release-tag>" >&2
	exit 1
fi

if [[ ! "${TAG_NAME}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Release tags must use the vX.Y.Z format; received ${TAG_NAME}." >&2
	exit 1
fi

RELEASE_VERSION="${TAG_NAME#v}"

json_version() {
	php -r '
		$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
		echo $data["version"] ?? "";
	' "$1"
}

check_version() {
	local source_name="$1"
	local actual_version="$2"

	if [[ "${actual_version}" != "${RELEASE_VERSION}" ]]; then
		echo "${source_name} has version ${actual_version:-<missing>}; expected ${RELEASE_VERSION}." >&2
		exit 1
	fi
}

PACKAGE_VERSION="$(json_version package.json)"
COMPOSER_VERSION="$(json_version composer.json)"
PLUGIN_HEADER_VERSION="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' cart-rebound.php | head -n 1)"
PLUGIN_CONSTANT_VERSION="$(sed -nE "s/^define\( 'CART_REBOUND_VERSION', '([^']+)' \);/\1/p" cart-rebound.php | head -n 1)"
STABLE_TAG="$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' readme.txt | head -n 1)"

check_version "package.json" "${PACKAGE_VERSION}"
check_version "composer.json" "${COMPOSER_VERSION}"
check_version "cart-rebound.php plugin header" "${PLUGIN_HEADER_VERSION}"
check_version "CART_REBOUND_VERSION" "${PLUGIN_CONSTANT_VERSION}"
check_version "readme.txt Stable tag" "${STABLE_TAG}"

if ! grep -Fqx "= ${RELEASE_VERSION} =" readme.txt; then
	echo "readme.txt has no changelog or upgrade-notice heading for ${RELEASE_VERSION}." >&2
	exit 1
fi

echo "Release version ${RELEASE_VERSION} is consistent."
