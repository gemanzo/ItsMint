# Development Environment Guide

This guide provides instructions for setting up and maintaining the development environment for this project.

## Table of Contents
1. [Introduction](#introduction)
2. [Docker Setup](#docker-setup)
3. [Pre-Commit Hooks (Husky, lint-staged, ESLint, Prettier)](#pre-commit-hooks)
    - [Overview](#overview)
    - [How It Works](#how-it-works)
    - [Troubleshooting](#troubleshooting)

---

## Introduction

A consistent and reproducible development environment is crucial for collaboration. This project uses Docker to containerize services and pre-commit hooks to enforce code quality and style standards automatically.

## Docker Setup

All project services (PHP, Nginx, Node, PostgreSQL, etc.) are managed via Docker. The main configuration files are `compose.yml` and `develop.compose.yml`.

-   **`compose.yml`**: Defines the base services and their interactions.
-   **`develop.compose.yml`**: Extends the base configuration for the local development environment.

To start the environment, run:
```bash
docker-compose -f develop.compose.yml up -d
```

## Pre-Commit Hooks

We use a combination of tools to automate code linting, formatting, and type-checking before any code is committed.

-   **Husky**: A tool that makes it easy to manage Git hooks.
-   **lint-staged**: Runs linters on files staged for a commit. This prevents having to lint the entire project.
-   **ESLint**: A pluggable linter for JavaScript and TypeScript.
-   **Prettier**: An opinionated code formatter.
-   **Pint**: A PHP code style fixer for Laravel.

### How It Works

1.  When you run `git commit`, Husky triggers the `pre-commit` hook.
2.  The hook executes `lint-staged`.
3.  `lint-staged` checks the configuration in `package.json` and runs the specified commands only on the staged files that match the patterns.
    -   `*.php` files are formatted with `pint`.
    -   `*.{js,ts,vue}` files are fixed by `eslint` and formatted by `prettier`.
4.  If any of the linters or formatters report an error and cannot fix it automatically, the commit is aborted. This ensures that no code violating the project's standards is ever committed.

### Troubleshooting

Running linters within a Dockerized environment can lead to specific issues. Here are common problems and their solutions.

#### Error: `Current directory is not a git directory!`

-   **Cause**: The Husky hook runs inside the `node` container, which doesn't have access to the `.git` directory by default, as it typically resides at the project root on the host machine.
-   **Solution**: You must map the host's `.git` directory as a volume into the container.

In your `compose.yml`, find the `node` service definition and add the `.git` volume:

```yaml
services:
  node:
    # ... other configurations
    volumes:
      - ".:/var/www/html/${DOCKER_PROJECT_DIR_NAME}"
      # Add this line to map the .git directory
      - "./.git:/var/w ww/html/${DOCKER_PROJECT_DIR_NAME}/.git"
      - "/var/www/html/${DOCKER_PROJECT_DIR_NAME}/node_modules"
    # ... other configurations
```
After adding this, restart your containers with `docker-compose -f develop.compose.yml up -d --force-recreate`.

#### Error: `Cannot find package '...'` or `Error: spawn ... ENOENT`

-   **Cause**: This happens when `lint-staged` tries to execute a command (e.g., `eslint`, `prettier`) and cannot find it. This is often because `npx` is used within the `lint-staged` configuration, which can behave unpredictably inside a Docker container or if the `PATH` is not correctly configured. The `ENOENT` error specifically means the command executable was not found.
-   **Solution**: Avoid `npx` and instead use the direct path to the binaries installed in `node_modules`.

In `package.json`, update your `lint-staged` configuration like this:
```json
"lint-staged": {
  "*.php": [
    "./vendor/bin/pint"
  ],
  "*.{js,ts,vue}": [
    "./node_modules/.bin/eslint --fix",
    "./node_modules/.bin/prettier --write"
  ]
}
```

#### General Advice: Run Commands Inside the Container

If you encounter persistent issues with dependencies or Node.js versions, remember that all `npm` commands must be run inside the `node` container to ensure you are using the correct environment.

```bash
# Example: Reinstalling all dependencies
docker exec node_itsmint sh -c "rm -rf node_modules && npm install"

# Example: Installing a new dependency
docker exec node_itsmint sh -c "npm install package-name"
```
This ensures consistency and avoids version conflicts with your local machine's Node.js environment. 