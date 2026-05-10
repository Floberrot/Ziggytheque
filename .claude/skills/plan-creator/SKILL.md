---
name: plan-creator
description: "Produces detailed implementation plans, written to `.agents/plans/`, that another engineer with zero prior context can execute task by task. ALWAYS invoke BEFORE any non-trivial implementation whenever the user asks for a plan, approach, design, or strategy; describes a multi-step feature, refactor, or migration; provides a spec or requirements; says 'how should we', 'propose', 'plan this', 'design the approach', 'draft a plan'; or requests work that spans multiple files, layers, or stacks. DO NOT edit code, run tests, or modify anything during planning — plan only. SKIP for trivial single-file edits, one-line bug fixes, and pure questions."
---

> Based on https://github.com/obra/superpowers/blob/main/skills/writing-plans/SKILL.md.

# Plan Creator

Write implementation plans that an engineer with zero context on this codebase can execute. Each task names exact files, key signatures/types, and verification — no placeholders.

**Announce at start:** "I'm using the plan-creator skill to create the implementation plan."

## First: load all context before drafting anything

Skills and docs serve **two audiences** at different times, and you MUST handle both:

- **Now (you, the plan-creator)** — you must actually load and read every skill / doc that could affect the plan, so the plan reflects the project's real conventions instead of generic guesses.
- **Later (the implementer)** — whoever picks up the plan loads skills/docs only as they reach each task. So **every task in the plan must list the specific skills and docs it needs**, even if you have already loaded them yourself. Without per-task lists, the implementer either over-loads everything (context bloat) or under-loads (wrong conventions on that task).

These two are not redundant: you load globally to plan; the implementer loads selectively to execute. Skipping either side causes a real failure.

Before writing any part of the plan — including the TL;DR — you MUST:

1. **Identify and load every skill that could match the work** (vue, symfony, project-quality-setup, frankenphp, …). Do this exhaustively, not lazily — missing a skill means the plan will assume false generic rules instead of this project's conventions.
2. **Identify every related file referenced from this skill or from the loaded skills** that applies to the task. Start with the architecture and coding-rules docs for the impacted stacks (load only those touched by the issue):
    - Server: `.claude/backend.md` and `.claude/CLAUDE.md`

   **Doc and skill files frequently reference other useful files (architecture diagrams, sub-guides, fixture lists, …); follow those references transitively** until you have the full picture for the impacted stacks.
3. **Display the full list of skills to load and files to read to the user as internal reasoning.** Format it as three explicit checklists:
    - **Skills to load**: one bullet per skill, with a short note of which task(s) will need it.
    - **Skills explicitly skipped**: every other skill currently available in this session that you decided NOT to load, each with a one-line reason for skipping. The list of available skills is provided to you in the system reminder — go through it and account for **every** entry that you did not include above. This forces you to consciously rule each skill in or out, instead of silently forgetting one.
    - **Doc files**: one bullet per file path, with a short note of its purpose for this plan and which task(s) need it. Listing the purpose forces you to confirm why each file matters; entries with no clear purpose should be dropped.

   Every task in the final plan must then list — under a single **Skills and docs to load** block — the subset of these skills and docs it actually needs.

   Since files reference other files transitively, you will discover new entries as you read — append them to the same checklist as you go (with their purpose / consuming task), so the user can audit your context-gathering and catch a missing reference before you draft the plan.

Skip skills and files that clearly do not apply to the impacted stacks — but err on the side of including a reference rather than omitting it.

## Plan-only contract

This skill produces a plan. Implementation happens separately, after the plan has been reviewed and approved.

- MUST NOT edit code, run build/migration/deploy/test commands, or modify tests.
- MUST NOT offer to execute the plan.
- If the request mixes in implementation, refuse it and return only the plan.

## Destination

Write the plan where the caller asked — inline reply, issue/PR comment, or an explicit path; otherwise default to `.agents/plans/YYYY-MM-DD-<feature-name>.md`.

> If the spec spans independent subsystems (front + back + tests) that could ship alone, suggest splitting into one plan per subsystem.

## Exploring existing code

When spawning an Explore subagent to understand the current implementation:
- **Always pass the absolute repo root path** in the prompt and tell the subagent to use absolute paths in all Glob calls (e.g. `<repo_root>/front/src/pages/*Manga*`). Relative Glob paths may silently return no results if the subagent's CWD differs.
- **Include the feature context** (what the ticket/issue is asking for) in the subagent prompt so it can focus its exploration on what is relevant, rather than doing a generic survey.
- When searching for component names, use case-insensitive Grep (`-i`) rather than exact-case Glob patterns.

## Language

- **Default: French** — By default, write the entire plan in French. Technical terms must remain in English. "franglais autorisé". Code snippets must be English.
- **English mode** — If asked or if all your instructions about the current task are in English, write the plan body in English only.

## Plan structure

A plan has three ordered parts:

1. **TL;DR** — makes the plan understandable end-to-end without reading the following. 1-3 paragraphs. Understandable by a Product Owner with a tech background. No file names, no function or type names. Lists allowed.
2. **Implementation** — The whole description of the current state, the choices made, the approach, the key trade-offs, the big implementation choices. Always explain the current state (ascii schema, pattern, mermaid diagram, ...) and the target. Lists and tables encouraged. Cover whatever is needed to grasp the implementation at a high level: data flow, new concepts, scope boundaries, rollout / rollback notes, risks. Omit parts that are not useful — conciseness wins over exhaustiveness, no empty placeholders.
3. **Tasks** — technical details only (paths, signatures, commands, scenarios). Never restate what the TL;DR already covers.


```
### TL;DR

> [!NOTE]
> [1-3 paragraphs — implementation choice in plain prose, no file names.]

### Implementation

[Everything needed to fully understand the implementation at a high level.]

---

### Tasks

- task 1: [description]
- task 2: [description]
- task 3: [description]

---

[Task 1: …]
```

**Writing to GitHub issues:** if you are using the `gh` CLI, always write the body to a temp file and pass it with `--body-file` — never inline the body in the shell command. Inline body strings cause backticks to be escaped (`` `file.js` `` → `` \`file.js\` ``) and break Markdown rendering.

## Task template

Pick a shape per task:
- **Test-first (TDD)** — pure, deterministic logic (domain rules, utils, validation).
- **Implement-then-verify** — default for most work (test backend features, UI, migrations, refactors).
- **No test** — translations, config, CI, pure docs. Justify the choice.

Don't restate project-wide testing defaults or stack commands — name the specific tests and commands that belong to each task.

```
#### Task N: [Name]

1–2 sentence goal.

**Skills and docs to load:**
- `/any-skill` — short reason this task needs it (e.g. "writing the .feature scenarios for the new endpoint")
- `docs/path/to/file.md` — short reason this task needs it

**Files:**
- Create `path/to/file`
- Modify `path/to/existing:123-145`
- Delete `path/to/orphan`
- Test `path/to/test`

**Implementation**

Approach, key signatures/types, touched public APIs, remarks, and non-obvious decisions or dependencies. Don't paste the full source — point to the files and describe the change.

**Tests (skip if no test)**

Scenarios to cover, where they live (file, Behat feature, Playwright spec), and how to identify a pass vs fail. Include small snippets only when a signature or assertion is non-obvious.
For Behat tasks (only), a full Gherkin scenario matching our `behat` skill guidelines is encouraged.
For Playwright tasks, describe the scenario in prose and name the `test(...)` title — never in Gherkin.

**Verify (skip if not relevant)**

Concrete commands the implementer will run, plus what passing looks like (specific output, exit code, or assertion). Verify is the gate that flips a task from `in-progress` to `done` — if a task cannot be verified by running something, drop the section rather than write an aspirational check.
```

## Mandatory final task

Every plan MUST end with a wrap-up task numbered as the highest task index in the plan. Use this exact body — do not rephrase or add scope:

```markdown
**Task [MAX]: Final lint, test, and review loop.**
Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
```

## No vague placeholders

Plans describe intent, not source code — but they must still be concrete. Never write: "TBD", "TODO", "implement later", "add appropriate error handling", "write tests for the above", "similar to Task N", or steps that describe *what* without naming the files, signatures, and scenarios involved.

## Don't repeat content from skills the implementer will load

The implementer loads `translations` upfront, then any skill a task names in its `Skills and docs to load` block.
Anything already documented in those skills does **not** belong in the plan — name the skill and trust the implementer to load it.

## Self-review before completion

1. **TL;DR and Implementation self-sufficiency** — a reader who stops after the TL;DR OR Implementation should understand 100% of what will be built, how, and why. If they need to open a task to get the picture, move that content up.
2. **No Implementation / task duplication** — tasks carry only technical details (paths, signatures, scenarios, commands). Anything already in the Implementation must not be repeated.
3. **Implementation contains the current and target states** — ASCII schemas, tables, ... Any reader must be able to understand what will be done, why, and how.
4. **No empty task sections** — drop `Tests` or `Verify` blocks when they have no content rather than leaving a placeholder.
5. **Spec coverage** — point to a task for every spec requirement; add what's missing.
6. **Placeholders** — scan for the red flags above and fix them.
7. **Skill duplication** — scan the plan for content that repeats what a per-task skill already provides. Replace with a skill reference.
8. **Type consistency** — function/type/property names agree across tasks (`clearLayers()` in Task 3 vs `clearFullLayers()` in Task 7 is a bug).
9. **Refactoring and cleanup** — Every change that obsoletes code must plan its removal explicitly: a `Delete` entry in the originating task's Files block when the orphan is task-scoped, or a dedicated cleanup task when orphans span multiple tasks — and every removal must also appear in an Implementation-level "Code being removed" list for one-place audit.
10. **Skills-and-docs list is exhaustive per task** — Every single task must carry a `Skills and docs to load` block listing every skill that matches its work AND every doc the implementer needs to read for that specific task, derived from the global checklists you produced upfront. Ensure no applicable skill or doc has been forgotten. Remove `/autonomous-developer-agent` from the list: it's always loaded.
11. **Final wrap-up task is present** — the last task number is the lint/test/file-reviewer loop from the "Mandatory final task" section, with the body copied verbatim.

Finish by summarizing only the plan destination and key phases.