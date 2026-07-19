#!/usr/bin/env bash

set -Eeuo pipefail

environment="${1:-}"
tag="${2:-}"
commit="${3:-}"
bundle_directory="${4:-}"
staging_verification="${5:-}"
script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

[[ "$environment" == production || "$environment" == staging ]] || die 'The environment must be production or staging.'
[[ "$commit" =~ ^[a-f0-9]{40}$ ]] || die 'The release commit is invalid.'
[[ -d "$bundle_directory" ]] || die 'The release bundle directory does not exist.'
[[ "${GITHUB_REPOSITORY:-}" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || die 'GITHUB_REPOSITORY is invalid.'
[[ "$("$script_dir/classify-release.sh" "$tag")" == "$environment" ]] || die 'The tag does not match the release environment.'

if [[ "$environment" == staging ]]; then
    [[ -f "$staging_verification" ]] || die 'The staging verification does not exist.'
elif [[ -n "$staging_verification" ]]; then
    die 'Production releases cannot include a staging verification for the stable tag.'
fi

remote_commit="$(gh api \
    -H 'X-GitHub-Api-Version: 2026-03-10' \
    "/repos/$GITHUB_REPOSITORY/commits/$tag" \
    --jq '.sha')"

[[ "$remote_commit" == "$commit" ]] || die 'The release tag moved after validation.'

if release_json="$(gh api \
    -H 'X-GitHub-Api-Version: 2026-03-10' \
    "/repos/$GITHUB_REPOSITORY/releases/tags/$tag" 2>/dev/null)"; then
    jq --exit-status '.draft == true' <<<"$release_json" >/dev/null \
        || die 'The release is already published and immutable.'
else
    create_options=(--draft --generate-notes --verify-tag)

    if [[ "$environment" == staging ]]; then
        create_options+=(--prerelease --latest=false)
    fi

    gh release create "$tag" "${create_options[@]}"
fi

shopt -s nullglob
charts=("$bundle_directory"/holizuki-*.tgz)
(( ${#charts[@]} == 1 )) || die 'The release bundle must contain exactly one Helm chart.'

assets=(
    "${charts[0]}"
    "$bundle_directory/environment-values.yaml"
    "$bundle_directory/release-manifest.json"
)

if [[ "$environment" == staging ]]; then
    assets+=("$staging_verification")
fi

for asset in "${assets[@]}"; do
    [[ -f "$asset" ]] || die "Missing release asset: $asset"
done

gh release upload "$tag" "${assets[@]}" --clobber

if [[ "$environment" == staging ]]; then
    gh release edit "$tag" --draft=false --latest=false --prerelease
    expected_prerelease=true
else
    gh release edit "$tag" --draft=false --latest --prerelease=false
    expected_prerelease=false
fi

published_release="$(gh api \
    -H 'X-GitHub-Api-Version: 2026-03-10' \
    "/repos/$GITHUB_REPOSITORY/releases/tags/$tag")"
jq --exit-status \
    --argjson prerelease "$expected_prerelease" \
    '.draft == false and .immutable == true and .prerelease == $prerelease' \
    <<<"$published_release" >/dev/null \
    || die 'GitHub did not publish the release in the expected immutable state.'
jq --raw-output '.html_url' <<<"$published_release"
