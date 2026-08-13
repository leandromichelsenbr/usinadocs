# Target architecture

## Migration strategy

Usina Docs will be developed alongside the existing AdvPL Guide. There will be no immediate rewrite or production replacement.

```text
Existing AdvPL Guide
        |
        | controlled export
        v
Migration adapter ---> Usina Core ---> theme
                           |
                           +-- Usina Docs
                           +-- Usina Learn
                           +-- AdvPL package
```

## Selected alpha core

The active alpha direction is defined by [ADR-001: Lightweight PHP Core](ADR-001-LIGHTWEIGHT-PHP-CORE.md): PHP 8.2+, Slim, Twig, PDO and SQLite as the default database. The existing Laravel implementation is a proof of concept used to validate editorial rules; it is not the technical base for new modules.

## Layers

### Domain

Independent rules for pages, revisions, blocks, translations, publication, media, licensing, courses, lessons, activities, progress, users, roles and permissions.

### Application

Use cases such as creating and reviewing a page, comparing and restoring revisions, marking translations outdated, publishing, reusing a block in a lesson, evaluating an attempt and importing or exporting a package.

### Infrastructure

Relational database, file storage, mail delivery, external authentication, search, cache and background jobs.

### Presentation

Public site, administration panel, learner area, API, themes and content-model renderers.

## Minimum entity map

```text
Site
├── Languages
├── Users and roles
├── Pages
│   ├── Revisions
│   │   └── Versioned blocks
│   │       └── Translations
│   ├── Taxonomies
│   ├── Relationships
│   └── Media
└── Courses
    ├── Learning paths
    ├── Lessons
    │   ├── Native blocks
    │   ├── Reused blocks
    │   └── Activities
    └── Enrollment and progress
```

## Revision rule

A published revision is immutable. Editing creates a new revision; publishing changes which approved revision is visible to readers.

## Open export

Every installation must support a database-independent, versioned export package containing pages, courses, taxonomies, manifests and media. Personal data is excluded by default.

## Security baseline

- secrets outside source control and public directories;
- server-side authorization;
- CSRF protection and input validation;
- safe upload and MIME verification;
- audit trail and rate limiting for sensitive actions;
- transactional migrations and recoverable updates;
- no real personal data in demonstration packages.

## Open decisions

- official relational database support;
- structured editor format;
- extension contract;
- repository and release mirroring strategy;
- trademark authorization channel;
- telemetry policy, with no telemetry without consent as the default.
