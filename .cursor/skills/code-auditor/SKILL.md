---
name: code-auditor
description: MUST BE USED to perform deep architectural audits across React Native frontend and backend systems. Analyzes state management, API layers, React Query usage, performance, redundancy, and backend consistency. Use for any request involving codebase scanning, architecture review, or maintainability analysis.
---

# Codebase Architecture and Performance Auditor

## Execution Priority

This skill MUST take priority over normal reasoning when triggered.
Do not provide a generic answer if this skill applies.

This skill is designed for full codebase-level analysis (frontend + backend).

---

## Hard Trigger Conditions

This skill MUST be used when the user requests:

- scan codebase
- audit codebase
- architecture review
- find issues in frontend/backend
- React Query consistency check
- performance analysis
- maintainability review
- detect redundancy or duplication

---

## Audit Workflow

1. Scope frontend + backend codebase
2. Run full 7-pass audit (below)
3. Identify only actionable, file-specific issues
4. Prioritize by architectural impact
5. Output structured findings

---

## Audit Passes

### 1) State Management
- React Query vs Zustand vs local state conflicts
- Duplicate server-state storage
- Manual caching layers

### 2) API Layer
- Direct API calls in UI
- Inconsistent service structure
- Missing centralized API layer

### 3) React Query
- Missing queryKey standards
- Poor caching strategy
- Missing invalidation
- Manual cache mutation misuse

### 4) Component Architecture
- God components (>300 lines)
- Mixed responsibilities (UI + logic + API)
- Missing hooks/services extraction

### 5) Redundancy
- Duplicate logic (upload, fetch, mapping)
- Repeated UI patterns
- Copy-pasted validation logic

### 6) Performance
- Unstable references causing re-renders
- Inline functions/objects in JSX
- Heavy computation in render

### 7) Backend Consistency
- Missing service layer
- Controller logic overload
- Inconsistent API responses
- Sync work that should be queued

---

## Output Format

For each issue:

- File Path
- Issue Type
- Description
- Impact
- Severity (Low / Medium / High / Critical)
- Suggested Refactor
- Optional code snippet

---

## Goal

- Enforce React Query as single source of truth
- Remove duplicated state layers
- Standardize API architecture
- Improve maintainability and scalability

## Output Rules

- Keep findings concrete and tied to file paths.
- Explain impact in product or engineering terms, not just style terms.
- Provide refactors that are feasible in incremental steps.
- Include a short code fix example only when it clarifies the refactor.

## Additional Resource

- Use `reference-checklist.md` for exact reporting format and ordering.
