#!/bin/bash
# SessionStart hook: makes sure `graft` (context graph, see docs/... or
# .claude/skills/graft/SKILL.md) is installed and the local graph is built,
# so a brand-new remote session (fresh container, nothing but git-tracked
# files) has it ready without manual setup. Only the wiring under .claude/
# and .mcp.json is committed -- the `graft` binary and the graft/ graph
# itself are NOT (regenerable, like node_modules), so this is what
# regenerates them each session.
#
# Idempotent and non-interactive: safe to run on every session start,
# no-ops in ~instant time once graft is already installed and built.
set -euo pipefail

# Local/desktop Claude Code sessions on this repo shouldn't get a silent
# global npm install pushed on them -- only do this in the remote/web
# environment this hook was written for.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"

if ! command -v graft >/dev/null 2>&1; then
  echo "[session-start] graft not found, installing @nanonets/graft..." >&2
  if ! npm install -g @nanonets/graft >/dev/null 2>&1; then
    echo "[session-start] graft install failed -- continuing without it (graft's own hooks no-op safely)" >&2
    exit 0
  fi
fi

if command -v graft >/dev/null 2>&1; then
  # Anonymous telemetry is opt-out; this repo's team decided to keep it off
  # (see the conversation that added this hook) -- reapply on every fresh
  # install since the setting lives in ~/.graft/, not in this repo.
  graft telemetry disable >/dev/null 2>&1 || true

  if [ ! -d "$PROJECT_DIR/graft" ]; then
    echo "[session-start] building graft graph..." >&2
    if ! (cd "$PROJECT_DIR" && graft build >/dev/null 2>&1); then
      echo "[session-start] graft build failed -- continuing without a graph" >&2
    fi
  fi
fi
