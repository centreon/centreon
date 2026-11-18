# Bruno API for the Web API

## Overview

This documentation covers the Bruno CLI v3 implementation for the `web-api-workspace` collection used to test the Centreon Web API.

## Prerequisites

- Bruno CLI v3 installed
- Node.js 24 or later
- A running Centreon instance exposing the Web API (typically a Centreon Web Docker container) reachable at the configured base URL

## Installation

```bash
pnpm install --frozen-lockfile
```

## Init the fixtures on containers

Run the setup script from anywhere in the repository:

```bash
./centreon/tests/api/web-api-workspace/global-setup.sh
```

## Running Tests

### Execute the Collection

```bash
# Navigate to the collection folder
cd web-api-workspace

# Run the collection with workspace configuration
npx bru run ./[COLLECTION_FOLDER_NAME] --workspace-path ./../.. --global-env Web-global-environment
```

**Important**: You must navigate into the collection folder before executing the `bru run` command. The `--workspace-path` parameter points to the workspace root (two levels up), and `--global-env` specifies the global environment configuration file.

## Excluding a collection from CI

To exclude a collection from the CI pipeline, add `tags: ignore` in its `collection.bru` meta block:

```
meta {
  name: My Collection
  tags: ignore
}
```

## Troubleshooting

- Check environment variables are properly set in `environments/Web-global-environment.yml`
- Ensure API endpoint is accessible
- Review Bruno CLI logs for detailed error messages
