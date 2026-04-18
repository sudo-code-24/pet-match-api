---
name: feature-implementation-engine
description: End-to-end feature implementation for React Native apps and their backends—screens, modules, APIs, workflows, integrations, and system enhancements—with clean architecture, reusable patterns, and aligned contracts. Use when the user asks to implement or add features, CRUD, endpoints, forms, wizards, uploads, auth, payments, notifications, or full-stack changes spanning UI and server.
---

# Feature Implementation Engine (React Native + Backend)

## High-priority triggers

Activate when the user mentions: implement/add a feature, new module/screen/endpoint, workflow/flow, integrate API or service, CRUD, form/wizard/process, backend endpoint, upload/auth/payments/notifications, or extending the system.

Also activate when the work spans **frontend + backend**, introduces a **new domain**, or requires **API + UI + server state** to stay in sync.

## Guarantees

- Align **frontend and backend contracts** before wiring UI.
- **No duplicated API logic**—single service layer on the client; thin controllers + services on the server.
- **Follow existing project patterns** (folder layout, naming, error shape, auth).
- **React Query** for server state (queries, mutations, cache); local state only for UI.
- Backend: **service-layer separation** (thin controllers, business logic in services, DB via repository layer when the codebase uses it).

## Implementation pipeline

### 1. Feature decomposition

Split work into: UI (screens/components), state (hooks + React Query), client **service layer** (API calls), backend (routes/controllers/services/repository/DB as applicable).

### 2. API contract design

Define request/response shapes first; keep naming and nesting consistent across client types and server validation.

### 3. Backend

Add or extend: thin controller, service with business rules, repository/DB access if needed. Validate inputs; return consistent success/error payloads.

### 4. Frontend

Add screens/modules using shared components; **no `fetch` in UI**—call through the service layer; wire **React Query** for reads and writes.

### 5. State integration

Use React Query for fetching, caching, and mutations; reserve component/hook state for UI-only concerns.

### 6. Reusability

Extract `useFeatureX`-style hooks and shared components; avoid copy-paste across screens.

### 7. Validation and edge cases

Loading, error, and empty states on the client; validation on client and server; resilient handling of network/API failures.

## Architecture rules

| Layer        | Responsibility                                      |
|-------------|------------------------------------------------------|
| Frontend UI | Presentation only                                   |
| Hooks       | Composition and UI-adjacent logic                   |
| Services + React Query | Network I/O and server state             |
| Routes      | Map HTTP to controllers                             |
| Controllers | Thin: parse, call service, respond                  |
| Services    | Business logic                                      |
| Repository  | Persistence when used in this codebase              |

## Output format

After substantive implementation, summarize:

- **Feature summary** — what shipped and user-visible behavior.
- **Frontend changes** — screens, components, hooks, query keys.
- **Backend changes** — routes, controllers, services, schema/migrations if any.
- **API contract** — endpoints, methods, request/response shapes.
- **File paths** — main touched files.
- **Data flow** — UI → hook/query → service → API → service → DB (if applicable).
- **Risks / impact** — breaking changes, migrations, rollout notes.
- **Optional improvements** — only if clearly valuable and scoped.

## Success criteria

End-to-end behavior works; clear separation of concerns; no duplicated client API code; contracts match across stack; structure is easy to extend with new endpoints or screens.
