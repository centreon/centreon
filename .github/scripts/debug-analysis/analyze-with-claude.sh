#!/usr/bin/env bash
set -euo pipefail

PROMPT="$1"

claude -p "$PROMPT" \
  --tools "Read,Grep,Glob,Write" \
  --permission-mode bypassPermissions \
  --max-budget-usd 3 \
  --verbose \
  --output-format stream-json \
  | tee claude-debug-analysis.jsonl \
  | jq -R -r --unbuffered '
      try (fromjson) catch empty
      | if .type == "assistant" then
          (.message.content // [])[] |
          if .type == "tool_use" then "STEP: -> " + .name
          elif .type == "text" and ((.text // "") | length) > 0 then
            "STEP: " + ((.text // "") | .[0:200] | gsub("\n"; " "))
          else empty end
        elif .type == "result" then "STEP: === finished (" + (.subtype // "?") + ") ==="
        else empty end
    '
