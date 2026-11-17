# ScholarMatch 50-case CI suite

Run locally:

```bash
php tests/ci_50.php
```

The suite prints one result for each ID from TC-01 to TC-50 and writes:

- `ci-results/50-test-results.csv`
- `ci-results/50-test-report.md`

Verification scope:

- TC-01 to TC-08 execute the eligibility and local CV extraction PHP functions.
- TC-09 to TC-50 are source-contract checks against the current PHP/SQL implementation. They verify that expected implementation elements are present in the repository. They do not prove runtime database persistence, HTTP authorization behavior, browser interactions, accessibility, or performance.

The GitHub Actions workflow also runs the three pre-existing test scripts and PHP syntax checks. Do not describe this workflow as 50 full end-to-end tests.
