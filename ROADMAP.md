# Usina Docs Roadmap

This roadmap communicates direction rather than fixed delivery dates. Priorities may change as the AdvPL Guide pilot, controlled-document use cases and community feedback reveal new requirements.

## Phase 0 - Project foundation

- [x] establish the public repository;
- [x] adopt MPL-2.0;
- [x] publish initial governance documents;
- [x] document product vision and target architecture;
- [x] define the initial technical stack and supported environment (ADR-001);
- [ ] add automated checks for secrets, licenses and documentation;
- [ ] publish contribution and release workflows.

## Phase 1 - Lightweight core and pages

- lightweight PHP bootstrap, routing and database migrations;
- users, roles, sites and languages;
- structured pages, blocks and immutable revisions;
- editorial states, comparison, restoration and publication;
- initial import/export format;
- first representative content models, including article, reference page, function/command, controlled procedure and lesson.

## Phase 2 - Block-level translation

- translation states per block and language;
- source-change tracking;
- outdated-translation detection;
- translation review and coverage reports;
- localized routes and language preferences.

## Phase 3 - Media and references

- secure uploads and file revisions;
- authorship, origin, license and accessibility metadata;
- thumbnails and on-demand media loading;
- structured citations and references.

## Phase 4 - Controlled documents

- document codes, owners, reviewers, approvers and areas;
- version validity, review cycles and expiration alerts;
- approval workflow and audit trail;
- formal acknowledgement for documents that require awareness;
- links between controlled documents and required training.

## Phase 5 - Native learning

- courses, tracks, modules and lessons;
- reuse of documentation blocks in lessons;
- versioned exercises and assessments;
- error-guided review;
- progress, achievements and aggregated learning indicators.

## Phase 6 - AdvPL Guide pilot

- import representative pages and lessons;
- run a separate staging installation;
- compare accessibility, SEO, performance and translations;
- release gradually with immediate rollback capability.

## Phase 7 - General knowledge pilots

- prepare demonstration content outside programming;
- validate procedures and work instructions;
- validate educational content for science and technical subjects;
- test document acknowledgement, training evidence and reporting.

## Alpha milestone

Alpha releases will validate architecture and may contain incompatible changes. The first public Alpha must provide a documented end-to-end installation and a complete page creation, translation, publication and learning flow.

## Beta milestone

Beta begins when an independent tester can install Usina Docs on a clean server, create an administrator, publish multilingual content, create a lesson, upgrade the installation and restore a backup by following only the public documentation.

## Stable 1.0 milestone

Version 1.0 requires tested upgrades, backup and restoration, security review, accessibility validation, versioned APIs and export formats, complete administrator and developer documentation, and successful external installations.
