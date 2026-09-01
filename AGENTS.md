# Instructions for coding agents

## Didactic code comments

Usina Docs is also a learning-oriented project. When creating or changing code, add as many useful didactic comments as practical so that a reader can understand not only what the implementation does, but why it was designed that way.

Comments should explain, whenever relevant:

- the purpose and responsibility of a class, service, route, migration, template or test;
- the business rule or editorial invariant protected by the code;
- the sequence of a workflow and the reason for important ordering constraints;
- non-obvious data transformations, validation and compatibility decisions;
- transaction boundaries, rollback behavior and preservation of immutable revisions;
- security assumptions, permission checks and trust boundaries;
- edge cases, failure modes and deliberate limitations;
- the intent of a test and the regression it is meant to prevent.

Prefer comments immediately above the code they clarify. Use docblocks for public APIs and types when they add information that cannot be expressed clearly by names and signatures alone.

Do not add comments that merely restate a variable name, repeat the syntax line by line, preserve obsolete behavior, or hide unclear code. Improve names and structure first, then use comments to preserve context, reasoning and knowledge that the code cannot communicate by itself.

When changing existing code, keep accurate comments and update or remove comments that no longer describe the implementation. New behavior should include proportionate didactic commentary, especially in the editorial domain, content-package compatibility, migrations, authentication, authorization, revision history and backup or restoration flows.
