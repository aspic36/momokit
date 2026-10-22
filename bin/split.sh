#!/usr/bin/env bash
set -euo pipefail

# Publishes the monorepo packages to their read-only GitHub repositories
# used by Packagist. Run from anywhere inside the repository.
#
# Usage: bin/split.sh [tag]
#   bin/split.sh           push the current main branch to both split repos
#   bin/split.sh v0.1.0    push, then tag both split repos

ROOT="$(git rev-parse --show-toplevel)"
OWNER="${GITHUB_OWNER:-aspic36}"
TAG="${1:-}"

if [[ -n "$(git -C "$ROOT" status --porcelain)" ]]; then
    echo "Working tree is not clean. Commit or stash your changes first." >&2
    exit 1
fi

split_package() {
    local prefix="$1" repo="$2" branch="$3"

    echo "==> ${prefix} -> ${OWNER}/${repo}"
    git -C "$ROOT" branch -D "$branch" >/dev/null 2>&1 || true
    git -C "$ROOT" subtree split --prefix="$prefix" -b "$branch" >/dev/null
    git -C "$ROOT" push --force "https://github.com/${OWNER}/${repo}.git" "${branch}:main"

    if [[ -n "$TAG" ]]; then
        local work
        work="$(mktemp -d)"
        git clone --quiet --depth 1 "https://github.com/${OWNER}/${repo}.git" "$work"
        git -C "$work" tag -a "$TAG" -m "MomoKit ${TAG} (${repo})"
        git -C "$work" push --quiet origin "$TAG"
        rm -rf "$work"
        echo "    tagged ${TAG}"
    fi
}

split_package packages/core momokit-core split/core
split_package packages/laravel momokit-laravel split/laravel

echo "Done."
