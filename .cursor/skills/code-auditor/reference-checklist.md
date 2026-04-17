# Code Auditor Output Template

Use this template exactly for each issue:

```markdown
- File Path: `path/to/file`
- Issue Type: Architecture | Performance | Redundancy | State | API | Backend
- Description: <what is wrong>
- Severity: Low | Medium | High | Critical
- Impact Explanation: <why this matters>
- Suggested Refactor: <specific, practical next step>
- Optional Code Fix Example: <only when useful>
```

## Prioritization Order

1. Critical architecture issues (multiple state sources, god components)
2. API misuse and duplication
3. React Query misconfiguration
4. Performance issues
5. Minor cleanup or low-impact improvements

## Review Quality Bar

- Every finding must reference a real file path.
- Avoid duplicate findings that describe the same root cause.
- Prefer root-cause fixes over local patch fixes.
- If no major issues are found in a category, state that explicitly.
