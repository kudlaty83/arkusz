#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
exec podman compose up -d --build --wait
