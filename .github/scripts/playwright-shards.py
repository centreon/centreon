#!/usr/bin/env python3
"""Compute the Playwright e2e CI shards from the test folders.

Each "heavy" folder (>= HEAVY_SPEC_THRESHOLD spec files) gets its own named
shard; the remaining "light" folders are round-robin packed into a bounded
number of ``group-N`` shards. This keeps the number of parallel jobs (and thus
the number of Centreon stacks booted) bounded while still naming the heavy
folders explicitly.

Prints a JSON array of ``{"name": ..., "paths": ...}`` objects to feed a GitHub
Actions matrix. ``paths`` is a space-separated list of spec paths, relative to
the e2e-playwright project directory, to hand to a single `playwright test`
invocation.

Usage: playwright-shards.py <tests-dir>
"""

import glob
import json
import os
import re
import sys

# Number of buckets the single-spec folders are packed into.
LIGHT_GROUPS = 3
# A folder with at least this many spec files runs in its own shard.
HEAVY_SPEC_THRESHOLD = 2


def spec_count(tests_dir: str, folder: str) -> int:
    pattern = os.path.join(tests_dir, folder, "**", "*.spec.ts")
    return len(glob.glob(pattern, recursive=True))


def main() -> None:
    tests_dir = sys.argv[1] if len(sys.argv) > 1 else "tests"

    folders = sorted(
        name
        for name in os.listdir(tests_dir)
        if os.path.isdir(os.path.join(tests_dir, name))
    )
    for folder in folders:
        # Folder names flow into the job name, the blob file name and an
        # unquoted shell argument, so keep them to a safe, predictable charset.
        if not re.fullmatch(r"[a-z0-9][a-z0-9-]*", folder):
            sys.exit(f"::error::invalid test folder name: {folder!r}")

    heavy = [f for f in folders if spec_count(tests_dir, f) >= HEAVY_SPEC_THRESHOLD]
    light = [f for f in folders if spec_count(tests_dir, f) < HEAVY_SPEC_THRESHOLD]

    buckets: list[list[str]] = [[] for _ in range(LIGHT_GROUPS)]
    for index, folder in enumerate(light):
        buckets[index % LIGHT_GROUPS].append(folder)

    shards = [{"name": folder, "paths": f"tests/{folder}"} for folder in heavy]
    for number, bucket in enumerate(buckets, start=1):
        if bucket:
            shards.append(
                {
                    "name": f"group-{number}",
                    "paths": " ".join(f"tests/{folder}" for folder in bucket),
                }
            )

    print(json.dumps(shards))


if __name__ == "__main__":
    main()
