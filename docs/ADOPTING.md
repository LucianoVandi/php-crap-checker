# Adopting php-crap-checker on a Legacy Project

Most projects that add `crap-check` for the first time have a non-trivial number of violations. This document explains how to introduce the tool without disrupting the team and how to improve gradually over time.

---

## The wrong way: zero-tolerance from day one

The instinctive reaction when adding any quality gate is to set a strict threshold and fix everything red. On a legacy codebase this backfires in two predictable ways:

**It takes too long.** Hundreds of violations cannot be fixed in a single PR. The gate blocks everyone while the cleanup branch sits open for weeks.

**It produces meaningless tests.** When developers are under pressure to lower a number, they write coverage that exercises code paths without asserting anything useful. The CRAP score drops; the safety net does not improve. This is Goodhart's Law applied to software metrics: *when a measure becomes a target, it ceases to be a good measure.*

The goal is not a low CRAP score. The goal is a codebase where complex code is meaningfully tested. Use the score as a signal, not an objective.

---

## Strategy 1: the salami — start permissive, tighten over time

Set the initial threshold high enough that the current codebase passes, then lower it incrementally as the team improves the code.

**Step 1** — find your current worst-case score:

```bash
vendor/bin/crap-check check build/crap4j.xml --threshold=0 --format=json \
  | jq '.methods[0].crap'
```

**Step 2** — set the threshold slightly above that value so CI passes today:

```bash
vendor/bin/crap-check check build/crap4j.xml --threshold=500
```

**Step 3** — add a comment in your CI config explaining the current state and the target:

```yaml
# Current worst method: CRAP 480. Target: 30 by end of Q3.
- run: vendor/bin/crap-check check build/crap4j.xml --threshold=500
```

**Step 4** — lower the threshold by 20–30% each sprint or release cycle. Every time you lower it, one or two methods need attention. Small, focused, meaningful work.

This approach keeps CI green while creating steady, measurable progress.

---

## Strategy 2: the violation budget — cap the count, not the score

Instead of banning all violations above a threshold, allow a fixed number and reduce the budget over time.

```bash
# Allow up to 25 violations today; reduce by 5 each sprint
vendor/bin/crap-check check build/crap4j.xml --threshold=30 --max-violations=25
```

The `--max-violations` option makes CI succeed as long as the count stays within budget. If a new violation appears, someone must fix an existing one first — the budget does not grow.

This is useful when the threshold is already reasonable but too many methods are above it to fix at once. The rule becomes: *no regression allowed, improvement encouraged.*

Combine both options for a tighter gate:

```bash
# Threshold is the quality bar; max-violations is the debt ceiling
vendor/bin/crap-check check build/crap4j.xml --threshold=30 --max-violations=10
```

---

## Strategy 3: the baseline — block new violations only

The strictest approach that is still fair to the team: freeze the current violation list, then refuse any commit that makes things worse.

This is not yet implemented natively but can be approximated today:

1. Run `crap-check` and record the current violation count.
2. Store that count as a constant in your CI config.
3. Use `--max-violations=<count>` to enforce it.

Whenever a developer fixes a legacy method, lower the count. Whenever someone tries to merge code that adds a violation, the budget fails and they must fix one existing method first.

A dedicated `baseline` command that snapshots specific method names is planned for a future release.

---

## GitHub Actions: a complete progressive workflow

```yaml
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pcov
          coverage: pcov

      - run: composer install --no-interaction --prefer-dist

      - name: Generate Crap4J report
        run: php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml

      # Phase 1 (weeks 1–4): just measure, never fail
      # - run: vendor/bin/crap-check check build/crap4j.xml --threshold=9999

      # Phase 2 (weeks 5–8): cap violations at current count
      # - run: vendor/bin/crap-check check build/crap4j.xml --threshold=30 --max-violations=25

      # Phase 3 (target state): enforce threshold strictly
      - name: Check CRAP threshold
        run: vendor/bin/crap-check check build/crap4j.xml --threshold=30
```

Advance from one phase to the next when the team is comfortable. Each phase transition is a single-line change in CI.

---

## Talking to your team about CRAP score

Numbers invite comparison and judgement. Introduce the metric carefully.

**What to say:**

> "The CRAP score highlights methods that are both complex and not well-tested. It tells us where the next bug is most likely to hide. It is not a measure of developer skill."

**What to avoid:**

- Leaderboards or dashboards ranking developers by their methods' scores.
- Phrasing like "your code has a CRAP score of 200" — attach scores to methods, not people.
- Treating the score as a goal rather than a signal.

**Useful framing:**

A CRAP score of 30 on a method does not mean the method is broken. It means: *if we ever need to change this, it will be risky to do so without better tests.* That is useful information, not an accusation.

When a method consistently triggers violations despite reasonable coverage, the right question is "should we reduce its complexity?" — not "how do we trick the metric?"

---

## What to prioritize

Not all violations are equal. Focus first on methods that are:

- **Changed frequently** — check `git log --follow` to see what moves most
- **On critical paths** — payment, authentication, data import/export
- **Untested entirely** — coverage 0% with any complexity is high risk

A method with CRAP 80 in code that hasn't changed in three years and has no tests is lower priority than a CRAP 35 method in a billing service touched every sprint.

Use the score to start the conversation, not to end it.
