#!/usr/bin/env python3
"""Check the synopsis and summary of the nfpm package definitions.

The first line of the nfpm description is the deb synopsis (apt search/show),
rpm.summary is the rpm one. Both must be unique within the repository.
"""

import collections
import subprocess
import sys

import yaml


class Loader(yaml.SafeLoader):
    """SafeLoader ignoring custom tags (gorgone configuration uses !include)."""


Loader.add_multi_constructor("!", lambda loader, suffix, node: None)

DEBUGINFO_SUFFIX = "-debuginfo"


def first_line(text):
    lines = [line.strip() for line in str(text or "").splitlines()]
    return next((line for line in lines if line), "")


def load_packages(paths):
    """Return {path: nfpm definition} for the nfpm files found in paths."""
    packages = {}
    for path in paths:
        if "packaging" not in path.split("/")[:-1]:
            continue
        with open(path, encoding="utf-8") as stream:
            content = yaml.load(stream, Loader=Loader)
        if isinstance(content, dict) and all(key in content for key in ("name", "arch", "version")):
            packages[path] = content
    return packages


def check(packages):
    """Return a list of (path, message) errors."""
    errors = []
    seen = {"synopsis": collections.defaultdict(list), "rpm summary": collections.defaultdict(list)}

    for path, package in sorted(packages.items()):
        name = str(package["name"])
        synopsis = first_line(package.get("description"))
        # nfpm uses the first description line when rpm.summary is not set
        raw_summary = (package.get("rpm") or {}).get("summary")
        summary = synopsis if raw_summary in (None, "") else first_line(raw_summary)

        if not synopsis:
            errors.append((path, f"{name}: the description is empty"))
            continue
        if not summary:
            errors.append((path, f"{name}: the rpm summary is blank"))
            continue

        fields = {"synopsis": synopsis, "rpm summary": summary}
        for field, value in fields.items():
            seen[field][value.casefold()].append((path, name))
            if name.endswith(DEBUGINFO_SUFFIX):
                base_name = name[: -len(DEBUGINFO_SUFFIX)]
                if base_name not in value.replace(",", " ").rstrip(".").split():
                    errors.append((path, f"{name}: the {field} does not mention {base_name}: '{value}'"))

    for field, values in seen.items():
        for owners in values.values():
            if len(owners) < 2:
                continue
            names = ", ".join(name for _, name in owners)
            for path, name in owners:
                errors.append((path, f"{name}: the {field} is shared by several packages ({names})"))

    return errors


def main():
    paths = sys.argv[1:] or subprocess.run(
        ["git", "ls-files", "*.yaml", "*.yml"], capture_output=True, text=True, check=True
    ).stdout.splitlines()

    packages = load_packages(paths)
    if not packages:
        print("::error::no nfpm package definition found")
        return 1

    errors = check(packages)
    for path, message in errors:
        print(f"::error file={path}::{message}")

    print(f"{len(packages)} package definitions checked, {len(errors)} error(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
