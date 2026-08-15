# Usina Docs

> [!WARNING]
> Usina Docs is in **pre-alpha**. Its first installable foundation is available for local evaluation only and is not ready for production use.

**Usina Docs** is an open source platform for knowledge management, controlled documentation and training. It helps teams create, structure, translate, review, publish, teach and track understanding of technical, operational and educational content. It is maintained by **Usina.BR Tecnologia e Informação Ltda. ME**.

Its central principle is simple: documentation and training share the same structured knowledge base. A concept, procedure, instruction, example, image or reference can be published for consultation and reused in lessons, reviews, assessments and evidence of training without editorial duplication.

## Project status

The repository contains the project's license, governance documents, vision, architecture planning and an archived Laravel proof of concept. The active direction is a lightweight PHP core; see [ADR-001](docs/ADR-001-LIGHTWEIGHT-PHP-CORE.md), the [content package specification](docs/CONTENT-PACKAGE.md) and the [roadmap](docs/en/roadmap.md).

The AdvPL Guide is the first real-world use case and the laboratory where the product requirements are being validated. Usina Docs itself will remain independent from AdvPL, Protheus and TOTVS-specific concepts.

The long-term goal is broader than programming documentation. Usina Docs is being designed to support knowledge bases, standard operating procedures, work instructions, controlled documents, technical manuals, internal training, onboarding, continuing education and subject areas such as programming, electronics, chemistry, physics, maintenance, quality and safety.

See the [project vision](docs/en/vision.md), [use cases and content models](docs/en/use-cases-and-models.md), [target architecture](docs/target-architecture.md) and [roadmap](docs/en/roadmap.md).

## Lightweight core preview

The installable lightweight core is being built in [`core/`](core/). It is intentionally isolated from the archived Laravel prototype while the project validates the new architecture.

It currently renders one published, Portuguese example page from SQLite. See [`core/README.md`](core/README.md) for local installation and validation steps.

## Planned capabilities

- structured page models and reusable content blocks;
- document-control metadata such as code, owner, approver, version, validity and review date;
- complete revision history, comparison and restoration;
- translation status and review at block level;
- media library with authorship, origin and licensing metadata;
- editorial workflows and permissions;
- acknowledgement, consent and training evidence for documents that require formal awareness;
- searchable catalogs, taxonomies and relationships;
- native courses, learning paths, lessons and assessments;
- progress, achievements and error-guided review;
- open import, export and backup formats;
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
