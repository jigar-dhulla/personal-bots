#!/usr/bin/env bash
# Image rollout for the production VPS.
#
# Two ways in:
#   1. The server's systemd timer (yaarpool-deploy.timer) runs this every couple
#      of minutes with DEPLOY_CHECK_DIGEST=1; it deploys only when the published
#      :latest image digest differs from the one last deployed. This keeps the
#      trigger outbound, so an IP-reputation IPS (CrowdSec) on the box can never
#      block a deploy the way an inbound SSH from GitHub's runners would.
#   2. The GitHub Actions deploy key's forced command runs it on demand; the
#      requested git ref arrives via SSH_ORIGINAL_COMMAND (defaults to main).
#
# Either way it first syncs itself and docker-compose.prod.yml from the public
# repo at that ref, then re-execs the fresh copy to roll out.

set -euo pipefail

cd "$(dirname "$(realpath "$0")")"

# Compose interpolates the ${HOME}/.wacli bind mount, so HOME must be set even
# when invoked from contexts that don't provide one (a systemd oneshot starts
# with no HOME). Without this the mount collapses to /.wacli and wacli loses
# its authenticated session. The SSH login shell already exports HOME=/root.
export HOME="${HOME:-/root}"

REPO="jigar-dhulla/personal-bots"
REF="${SSH_ORIGINAL_COMMAND:-main}"
STATE_FILE=".deployed-digest"

if ! [[ "$REF" =~ ^([0-9a-f]{40}|main)$ ]]; then
    echo "Refusing to deploy unrecognised ref: $REF" >&2
    exit 1
fi

# Digest the :latest tag currently resolves to in GHCR, read anonymously (the
# image is public) straight from the registry's Docker-Content-Digest header,
# so the check needs neither docker nor jq on the host.
remote_digest() {
    local token
    token=$(curl -fsSL "https://ghcr.io/token?scope=repository:$REPO:pull" \
        | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
    curl -fsSL -o /dev/null -D - \
        -H "Authorization: Bearer $token" \
        -H "Accept: application/vnd.oci.image.index.v1+json" \
        -H "Accept: application/vnd.docker.distribution.manifest.list.v2+json" \
        -H "Accept: application/vnd.oci.image.manifest.v1+json" \
        -H "Accept: application/vnd.docker.distribution.manifest.v2+json" \
        "https://ghcr.io/v2/$REPO/manifests/latest" \
        | tr -d '\r' | sed -n 's/^[Dd]ocker-[Cc]ontent-[Dd]igest: //p'
}

if [[ "${DEPLOY_SYNCED:-0}" != "1" ]]; then
    DIGEST=$(remote_digest || true)

    if [[ "${DEPLOY_CHECK_DIGEST:-0}" == "1" ]]; then
        if [[ -z "$DIGEST" ]]; then
            echo "Could not read remote image digest; skipping this cycle." >&2
            exit 0
        fi
        if [[ -f "$STATE_FILE" && "$(cat "$STATE_FILE")" == "$DIGEST" ]]; then
            exit 0 # already running the latest image, nothing to do
        fi
        echo "==> New image $DIGEST detected"
    fi

    echo "==> Syncing infra files from $REPO@$REF"
    curl -fsSL "https://raw.githubusercontent.com/$REPO/$REF/docker-compose.prod.yml" -o docker-compose.prod.yml.tmp
    curl -fsSL "https://raw.githubusercontent.com/$REPO/$REF/deploy/deploy.sh" -o deploy.sh.tmp
    mv docker-compose.prod.yml.tmp docker-compose.prod.yml
    mv deploy.sh.tmp deploy.sh
    chmod +x deploy.sh
    DEPLOY_SYNCED=1 DEPLOY_REMOTE_DIGEST="$DIGEST" SSH_ORIGINAL_COMMAND="$REF" exec bash deploy.sh
fi

export COMPOSE_FILE=docker-compose.prod.yml

echo "==> Validating compose file"
docker compose config --quiet

echo "==> Pulling latest images"
docker compose pull --quiet

echo "==> Running migrations"
docker compose run --rm app php artisan migrate --force

echo "==> Recreating services"
docker compose up -d --remove-orphans

echo "==> Pruning dangling images older than 24h"
docker image prune -f --filter "until=24h" >/dev/null

# Record what we just deployed so the timer stays quiet until it changes.
if [[ -n "${DEPLOY_REMOTE_DIGEST:-}" ]]; then
    echo "$DEPLOY_REMOTE_DIGEST" >"$STATE_FILE"
fi

echo "==> Status"
docker compose ps
