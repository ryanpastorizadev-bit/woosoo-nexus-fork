# Woosoo Nexus

Clean admin application built with:

- Laravel 12
- Vue 3
- Inertia.js
- Tailwind CSS
- shadcn-vue style components

## Current Scope

This repository is focused on the Woosoo Nexus admin experience first.

The immediate goal is a clean, maintainable admin UI and application foundation before adding production infrastructure, tablet deployment, print bridge workflows, or external service orchestration.

## Development

Install dependencies:

```bash
composer install
npm install
```

Run the Laravel and Vite development servers:

```bash
composer dev
```

Build frontend assets:

```bash
npm run build
```

Run backend tests:

```bash
php artisan test
```

Run frontend checks:

```bash
npm run typecheck
npm run lint:check
```

## Architecture Direction

Keep the app simple first:

```txt
Laravel controllers / routes
↓
Inertia pages
↓
Vue components
↓
Tailwind CSS + shadcn-vue primitives
```

Avoid coupling the admin foundation to deployment-specific concerns until the UI, routes, and data contracts are stable.

## Documentation

Canonical documentation should live under `docs/` once the clean admin foundation is finalized.
