# Use cases and content models

Usina Docs is designed to handle multiple knowledge domains with the same foundation: structured content, revisions, translations, references, training and evidence.

This document describes the main long-term use cases. It is not a final schema; it is a planning guide for future data models, interface design and acceptance criteria.

## Knowledge base

For searchable reference material, technical explanations and general documentation.

Typical fields:

- title, summary and status;
- subject, tags and relationships;
- creation and review dates;
- references and citations;
- translated versions;
- reusable content blocks.

Examples:

- programming concepts;
- technical articles;
- frequently asked questions;
- glossary entries;
- electronics, chemistry or physics notes;
- internal knowledge articles.

## Controlled document

For content that requires governance, review, approval or formal awareness.

Typical fields:

- document code;
- version or revision label;
- owner area;
- reviewer;
- approver;
- effective date;
- next review date;
- status;
- change history;
- required acknowledgement;
- required training.

Examples:

- standard operating procedures;
- work instructions;
- internal policies;
- quality documents;
- safety procedures;
- equipment manuals.

## Procedure or work instruction

For step-by-step operational content.

Typical blocks:

- objective;
- scope;
- required materials or tools;
- prerequisites;
- safety warnings;
- ordered steps;
- expected result;
- checklist;
- records generated;
- related documents.

Examples:

- machine operation;
- laboratory procedure;
- software support routine;
- document approval routine;
- maintenance instruction.

## Training content

For lessons, learning paths, assessments and progress tracking.

Typical fields:

- learning objectives;
- prerequisites;
- lesson steps;
- practice activities;
- assessment questions;
- passing score;
- error-guided review;
- achievement or certificate;
- linked documents or references.

Examples:

- AdvPL lessons;
- onboarding path;
- safety training;
- equipment training;
- internal process certification.

## Technical reference

For structured reference pages whose format should be predictable.

Typical fields:

- name;
- syntax or identification;
- purpose;
- parameters or inputs;
- return or expected output;
- examples;
- cautions;
- related pages;
- references.

Examples:

- programming functions;
- commands;
- classes;
- APIs;
- formulas;
- components.

## Educational subject

For subjects outside programming that still benefit from structured explanation and learning.

Typical blocks:

- concept explanation;
- formula or principle;
- practical example;
- diagram or media;
- experiment;
- review questions;
- references.

Examples:

- electronics;
- chemistry;
- physics;
- mathematics;
- mechanics.

## Product rule

The core must not hard-code any single domain. Programming, procedures, science lessons and controlled documents must be represented as content models, packages or extensions over the same generic platform.
