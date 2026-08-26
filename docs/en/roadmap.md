# Usina Docs Roadmap

This roadmap communicates direction rather than fixed delivery dates. Priorities may change as the AdvPL Guide pilot, controlled-document use cases and community feedback reveal new requirements.

## Next development cycle

1. visual revision history for pages, models and artifacts, including comparison and restoration;
2. source-change tracking and outdated-translation detection for pages and artifacts;
3. an initial import/export format validated with a representative AdvPL Guide content set;
4. editorial fields and models required for function references, classes, entry points and articles;
5. an inventory of URLs, redirects, SEO metadata and media from `advpl.usinabr.com.br`;
6. an isolated staging installation for the first migration rehearsal;
7. database upgrade, backup and restoration tests before the first Alpha.

## Phase 0 - Project foundation

- [x] establish the public repository;
- [x] adopt MPL-2.0;
- [x] publish initial governance documents;
- [x] document product vision and target architecture;
- [x] define the initial technical stack and supported environment (ADR-001);
- [ ] add automated checks for secrets, licenses and documentation;
- [ ] publish contribution and release workflows.

## Phase 1 - Lightweight core and pages

- [x] lightweight PHP bootstrap, routing and database migrations;
- [x] initial administrator access, sites and languages;
- [x] structured pages, blocks, drafts, publication and immutable revisions;
- [x] versioned editorial model editor;
- [x] versioned reusable artifact editor;
- [x] revision-pinned artifact references and impact map;
- [x] versioned page and artifact translations in Portuguese, English and Spanish;
- [x] visual revision history, comparison and restoration;
- [ ] initial import/export format;
- [ ] complete editorial roles and permissions beyond the administrator;
- [ ] complete representative models for function/command, controlled procedure and lesson content.

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
