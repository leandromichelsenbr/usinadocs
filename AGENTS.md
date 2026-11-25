# Instructions for coding agents

## Publishing changes

After validating a requested Usina Docs change, commit and publish it to the official repository at `https://github.com/leandromichelsenbr/usinadocs`. Use a pull request against the default branch unless the repository or the maintainer specifies a different workflow. Keep credentials, local configuration, and instance data outside published commits.

## Didactic code comments

Usina Docs is a public learning-oriented project. Whenever you create or edit source code, write didactic comments in both English and Portuguese for the code you add or change. Keep the two versions together and equivalent, with English first and Portuguese next. Explain the intent, how the code works, and why the implementation was chosen, with enough context for a reader learning from the source.

O Usina Docs é um projeto público voltado ao aprendizado. Sempre que criar ou editar um arquivo-fonte, escreva comentários didáticos em inglês e português para o código criado ou alterado. Mantenha as duas versões juntas e equivalentes, com o inglês primeiro e o português em seguida. Explique a intenção, o funcionamento e o motivo das escolhas de implementação, com contexto suficiente para quem está aprendendo pelo código.

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

When changing existing code, keep accurate comments and update both language versions together. Add a missing language to relevant comments in the changed code. Remove comments that no longer describe the implementation. New behavior should include proportionate didactic commentary, especially in the editorial domain, content-package compatibility, migrations, authentication, authorization, revision history and backup or restoration flows.
