# Changelog

All notable changes to Usina Docs will be documented in this file.

The project intends to use semantic versioning as it evolves toward its first stable release.

## Unreleased

### Security

- Application-wide CSRF protection: every POST must carry the session token, sent by all administrative, login, logout and comment forms; the token rotates at sign-in.
- Password-mode session cookies are now `HttpOnly` and `SameSite=Lax` with strict session mode.

### Removed

- Laravel 12 prototype from the main tree; it remains available at the `v0.1.0-laravel-prototype` tag (ADR-001).
- Laravel-specific GitHub workflows (issues, pull requests, changelog) and StyleCI configuration.

### Added

- Automatic deploy of `main` to news.usinabr.com.br after the core tests pass, through an SSH key restricted to a server-side deploy script with database backup and rollback (`deploy/usina-news/`).

- Optional news homepage (`USINADOCS_NEWS_HOME=1`) with latest articles and editorial sections, configurable site name and time zone, brought from the Usina News installation.
- Theme-aware colors for quiz feedback.

- Versioned editorial model editor with required artifact definitions.
- Versioned reusable artifact editor with revision-pinned page references.
- Artifact usage and update-impact reports across pages and languages.
- Versioned reusable artifact translations in Portuguese, English and Spanish.
- Model-driven page editing and required-artifact validation before publication.
- Bilingual README and strategic documentation in Portuguese and English.
- Initial browser-based editorial panel for pages, drafts, blocks and publication.
- Local administrator authentication, role protection and secure first-administrator command.
- Editorial core for sites, languages, pages, ordered blocks and immutable published revisions.
- Public page rendering from published revisions and seeded demonstration content.
- Initial Laravel 12 application foundation with local SQLite installation guidance.
- Public demonstration page rendered from structured content blocks.
- Automated test workflow for pull requests and the `main` branch, now running the `core/` suite on PHP 8.2 and 8.3.
- Initial open source project governance and documentation.
- Pre-alpha product vision and target architecture.
- Public roadmap, support guidance and community code of conduct.

### Changed

- Repositioned Usina Docs as a knowledge and learning platform.
- Expanded the long-term product scope to include controlled documents, procedures, work instructions and non-programming knowledge areas.
- Added initial planning for general use cases and content models.
- Clarified official logo usage and the distinction between software and trademark rights.
- Clarified the private vulnerability reporting process for the pre-alpha phase.
