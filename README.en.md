# Usina Docs

> [!WARNING]
> Usina Docs is in **pre-alpha**. Its first installable foundation is available for local evaluation only and is not ready for production use.

**Usina Docs** is an open source platform for knowledge management, controlled documentation and training. It helps teams create, structure, translate, review, publish, teach and track understanding of technical, operational and educational content. It is maintained by **Usina.BR Tecnologia e Informação Ltda. ME**.

Its central principle is simple: documentation and training share the same structured knowledge base. A concept, procedure, instruction, example, image or reference can be published for consultation and reused in lessons, reviews, assessments and evidence of training without editorial duplication.

## Project status

The repository contains the project's license, governance documents, vision, architecture planning and the lightweight PHP core. The archived Laravel proof of concept is kept only at the `v0.1.0-laravel-prototype` tag; see [ADR-001](docs/ADR-001-LIGHTWEIGHT-PHP-CORE.md), the [content package specification](docs/CONTENT-PACKAGE.md) and the [roadmap](docs/en/roadmap.md).

The AdvPL Guide is the first real-world use case and the laboratory where the product requirements are being validated. Usina Docs itself will remain independent from AdvPL, Protheus and TOTVS-specific concepts.

The long-term goal is broader than programming documentation. Usina Docs is being designed to support knowledge bases, standard operating procedures, work instructions, controlled documents, technical manuals, internal training, onboarding, continuing education and subject areas such as programming, electronics, chemistry, physics, maintenance, quality and safety.

See the [project vision](docs/en/vision.md), [use cases and content models](docs/en/use-cases-and-models.md), [target architecture](docs/target-architecture.md) and [roadmap](docs/en/roadmap.md).

## Lightweight core preview

The installable lightweight core lives in [`core/`](core/) and is the only active code base. Local installation is described in [docs/INSTALL.md](docs/INSTALL.md).

The core already provides:

- structured pages with ordered blocks, drafts, publication, immutable revisions and localized routes in Portuguese, English and Spanish;
- versioned editorial models and reusable artifacts (images, tables, citations, diagrams and quizzes), referenced by pinned revision, with usage and update-impact reports;
- versioned page and artifact translations, with review screens that flag a translation as outdated when its Portuguese source changes;
- revision history for pages, models and artifacts, with comparison and restoration as a new draft;
- content package export, import preview, transactional import and updates imported as new revisions, following the [content package specification](docs/CONTENT-PACKAGE.md);
- a media library with source and license notes, deferred image loading, safe YouTube embeds and article covers;
- articles with author profiles, bylines, moderated comments, view counts and coordinated trilingual releases;
- legal documents, site appearance settings and reader language and theme preferences;
- local administrator login or Google and Microsoft login with Reader, Editor and Administrator groups, and CSRF protection on every state-changing request;
- an optional news homepage (`USINADOCS_NEWS_HOME=1`) with the latest articles and editorial sections.

See [`core/README.md`](core/README.md) for local installation and validation steps.

## Planned capabilities

- translation status and review at block level;
- document-control metadata such as code, owner, approver, version, validity and review date;
- media file revisions and fuller authorship and accessibility metadata;
- editorial approval workflows and finer-grained permissions;
- acknowledgement, consent and training evidence for documents that require formal awareness;
- searchable catalogs, taxonomies and relationships;
- native courses, learning paths, lessons and assessments;
- progress, achievements and error-guided review;
- full backup and restoration, and stable versions of the import and export formats;
- themes and extension points.

## Open source

The official Usina Docs source code will remain publicly available.

The project may be used for personal or commercial purposes, studied, modified, redistributed and integrated into other solutions, including proprietary software, subject to the terms of the **Mozilla Public License 2.0 (MPL-2.0)**.

Usina.BR is committed to keeping the official project and its main codebase open source. See [OPEN_SOURCE_PLEDGE.md](OPEN_SOURCE_PLEDGE.md).

## License

Usina Docs is licensed under the **Mozilla Public License 2.0 (MPL-2.0)**. See [LICENSE](LICENSE) for the complete license text.

Copyright © 2026 Usina.BR Tecnologia e Informação Ltda. ME.

The license covers source code, not the Usina.BR or Usina Docs names, logos and official visual identity. See [TRADEMARKS.md](TRADEMARKS.md).

## Contributing

Community contributions are welcome. Pull Requests accepted into the project are incorporated under the same **MPL-2.0** license. See [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Support and security

Use of Usina Docs does not require a support contract. Usina.BR may optionally provide commercial support, implementation, training, consulting, integrations, hosting and development.

For community support expectations, see [SUPPORT.md](SUPPORT.md). Please report security vulnerabilities privately as described in [SECURITY.md](SECURITY.md).
