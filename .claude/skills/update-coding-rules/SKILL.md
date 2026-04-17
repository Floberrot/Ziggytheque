---
name: update-coding-rules
description: >
  Formalizes a spotted bad practice or missing rule into the project coding standards (CLAUDE.md and
  .claude/ files). Trigger when a bad pattern is found in code, a rule is missing, or the user says
  "add this to the rules", "we should always/never X", or "make sure we don't do this again".
metadata:
  author: floberrot
  version: "1.0.0"
---

# Update Coding Rules Workflow

This skill formalizes a spotted bad practice or missing rule into the project's coding standards.
Follow every step in order.

---

## Step 1 — Identify and articulate the issue

Before touching any file:

1. State clearly **what was observed**: quote the offending code or describe the pattern.
2. State **why it is wrong**: which architectural principle, style rule, or invariant does it violate?
3. State **what the correct pattern is**: show a minimal before/after.
4. Determine the **scope**: is this a one-off fix or a systemic gap that needs a rule?

If the scope is a one-off fix with no generalizable lesson, stop here — fix the code and do not add a rule.

---

## Step 2 — Choose where the rule belongs

Pick the right file based on the nature of the rule:

| Nature of the rule | Target file |
|---|---|
| Architecture, CQRS, domain purity, handlers, events | `CLAUDE.md` → **Architecture** section |
| Code style, types, `final`, `readonly`, FQCN, French | `CLAUDE.md` → **Code Style** section |
| Controller patterns, `MapRequestPayload`, no try/catch | `CLAUDE.md` → **Controllers** section |
| Exception rules (unique class, identifier, middleware) | `CLAUDE.md` → **Exceptions** section |
| PHPStan, phpcs, Deptrac, quality gates | `.claude/quality.md` |
| Backend layer structure, ports & adapters | `.claude/backend.md` |
| Frontend Atomic Design, Vue conventions, TanStack | `.claude/frontend.md` |
| Skill invocation triggers | `.claude/skills.md` |

If the rule fits multiple files, add the **primary** rule to the most specific file and a **cross-reference** note in the other.

---

## Step 3 — Draft the rule

Write the rule as a single, actionable sentence following this pattern:

> **[Verb] [subject]** — never/always [constraint]. [One-sentence rationale].

Examples:
- **Every exception must carry an identifier** — always pass the relevant ID, token, or email so logs and error responses contain actionable context.
- **Handlers perform exactly one action** — any side-effect must be triggered by a Domain Event handled in a dedicated `EventHandler`, never chained inside the same handler.
- **`readonly` on individual properties is redundant inside a `readonly class`** — apply `readonly` at the class level only.

Include a **bad** and **good** code snippet when the rule is non-obvious.

---

## Step 4 — Update the rule file

Edit the target file identified in Step 2:

- Insert the new rule under the correct section header.
- Keep the rule list sorted by importance (most critical first).
- If an existing rule is incomplete or misleading, update it in place rather than adding a duplicate.
- If a **code example** (bad/good) illustrates the rule clearly, add it.

After editing, re-read the entire section to ensure the new rule does not contradict any existing rule.

---

## Step 5 — Save a memory (if the rule is non-obvious)

If the rule captures a non-obvious decision — something that future Claude sessions would likely get wrong without this context — save a `feedback` memory:

```
Rule: [the rule, one sentence]
Why: [the motivation — incident, pattern, architectural reason]
How to apply: [when and where this kicks in]
```

Save to `/Users/floberrot/.claude/projects/-Users-floberrot-PhpStormProjects-Ziggy/memory/` and update `MEMORY.md`.

If the rule is already obvious from reading `CLAUDE.md`, skip this step.

---

## Step 6 — Report to the user

After all edits are done, output a concise summary:

```
## Rule added

**File:** `.claude/<target>.md` → `<Section>`

**Rule:** [the rule sentence]

**Before:**
[bad code snippet]

**After:**
[good code snippet]
```

Keep the summary short. No trailing commentary.

---

## Rules for this skill

- **Do not add rules that duplicate existing ones** — read the target section fully before inserting.
- **Do not add rules for things already enforced by tooling** (PHPStan, phpcs, Deptrac catch them automatically).
- **Do not soften rules** — if the correct pattern is always required, say "always" or "never", not "prefer" or "try to".
- **Do not invent rules** — every rule must be grounded in an observed problem or an explicit architectural decision.
- **One skill invocation = one rule** — if multiple rules need adding, run the skill once per rule or group tightly related rules into one section update.
