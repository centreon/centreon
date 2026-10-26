#!/usr/bin/env bash
set -uo pipefail

PROMPT="$1"

claude -p "$PROMPT" \
  --tools "Read,Grep,Glob" \
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
claude_status="${PIPESTATUS[0]}"

result_text=$(jq -R -r 'try (fromjson) catch empty | select(.type == "result") | .result // empty' claude-debug-analysis.jsonl 2>/dev/null | tail -n 1)
if [ -n "$result_text" ]; then
  mkdir -p debug-analysis
  printf '%s\n' "$result_text" > debug-analysis/summary.md
fi

exit "$claude_status"
