---
name: code-auto-refactor-engine
description: Auto-refactor engine for React Native + backend. Strongly activates when user requests refactoring, cleanup, architecture fixes, API standardization, React Query migration, or removal of redundancy. Prioritizes safe incremental improvements while preserving behavior.
---

# React Native and Backend Auto Refactor Engine

## WHEN THIS SKILL MUST BE USED (HIGH PRIORITY TRIGGERS)

Automatically activate this skill when the user says:

- refactor
- clean up code
- remove duplication / redundancy
- improve architecture
- optimize React Query
- fix API structure
- decompose component
- simplify screen / module
- improve maintainability
- standardize API layer
- fix state management issues

OR when the user requests changes involving:
- React Query usage
- API service restructuring
- large component breakdown
- backend controller/service cleanup

---

## USE THIS SKILL WHEN

- Any refactor request in React Native or backend codebase
- Any architecture cleanup request
- Any request involving state management consolidation
- Any request involving API restructuring or service layering

---

## REFRACTOR GUARANTEE RULES

- Never change business logic or UI behavior
- Always apply incremental changes
- Never rewrite entire files unless explicitly requested
- Always preserve API contracts
- Prefer extraction over rewriting

---

## REFACTOR PIPELINE

1. Detect scope (files/modules affected)
2. Identify redundancy + architecture issues
3. Apply incremental safe refactors
4. Validate consistency (React Query, API layer, state)
5. Report all changes clearly

---

## REFACTOR MODULES

### 1. State Management Normalization
- React Query = single source of truth for server state
- Remove duplicate Zustand/local server-state usage
- Keep Zustand only for UI state

---

### 2. API Layer Standardization
- Remove API calls from UI components
- Consolidate all API logic into service files
- Remove duplicated API functions

---

### 3. React Query Optimization
- Standardize queryKeys
- Replace manual cache updates with invalidateQueries when possible
- Ensure proper mutation invalidation

---

### 4. Component Decomposition
- Split god components into:
  - UI components
  - hooks
  - services
- Extract repeated logic into reusable modules

---

### 5. Redundancy Removal
- Remove duplicated logic across screens/services
- Merge repeated validation and mapping logic
- Extract shared utilities

---

### 6. Performance Optimization
- Memoize derived values and callbacks where needed
- Reduce unnecessary re-renders
- Optimize list rendering patterns

---

### 7. Backend Cleanup
- Move logic from controllers → services
- Normalize response structure
- Keep controllers thin
- Queue heavy tasks when needed

---

## OUTPUT FORMAT

- File Path
- What changed
- Why it changed
- Risk Level
- Before/After summary

---

## SUCCESS GOAL

- React Query is the single source of truth
- API layer is fully centralized
- Components are modular and thin
- No duplicated logic across codebase
- Backend follows service-layer architecture