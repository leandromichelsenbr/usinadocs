# Training Module — Usina Docs

## Vision

The **Training Module** will be a native, isolatable Usina Docs domain for creating, publishing and tracking learning programs, free courses, tracks, modules, lessons, exercises, assessments, progress, achievements and, later, completion certificates.

It should reuse Usina Docs editorial, multilingual, user and media services while keeping an explicit domain boundary so it can evolve relatively independently from the documentation core.

## Origin pilot

The first functional pilot currently lives in `leandromichelsenbr/advpl-usinabr-site`, in the AdvPL Guide training area. Its code has been separated under `training/` to facilitate a future migration to Usina Docs.

The pilot already provides real requirements for enrollment, lesson progress, points, quizzes, achievements, administrative indicators, multilingual content, a free course and a broader progressive learning program.

The migration should not simply copy the current PHP implementation. The pilot is a source of business rules, content and usage experience for a native Usina Docs implementation.

## Learning products

### Free course

Independent learning content outside a formal program. The initial example is the existing **AdvPL Training** course in AdvPL Guide.

### Learning program

A broader progressive competency-oriented path. The first planned program is **ADVPL & Protheus**:

| Level | Path |
|---|---|
| L0 | Programming Foundations |
| L1 | Protheus Development Environment |
| L2 | ADVPL Essentials |
| L3 | ADVPL + Data |
| L4 | Protheus Application Developer |
| L5 | Advanced Protheus Developer |
| L6 | Integration Developer |
| L7 | Modern Protheus Developer |

The curriculum remains under planning and does not imply fixed dates or workloads.

## Conceptual structure

```text
Training
├── Programs
│   └── Tracks / Levels
│       └── Modules
│           └── Lessons
├── Free Courses
│   └── Modules
│       └── Lessons
├── Exercises
├── Assessments
├── Enrollments
├── Progress
├── Achievements
└── Certificates
```

## Planned entities

- **Program** — broad versioned learning program with completion rules.
- **Track/Level** — progression, prerequisites, competencies and final project.
- **Free Course** — course independent from a formal program.
- **Module** — grouping of related lessons.
- **Lesson** — versioned multilingual learning unit, reusing editorial blocks whenever possible.
- **Exercise** — versioned practical activity.
- **Assessment** — quiz, evaluated exercise or project preserving the answered version.
- **Enrollment** — link between a user and a course/program.
- **Progress** — learner evolution by content and stage.
- **Achievement** — intermediate recognition for a skill or milestone.
- **Certificate** — later completion-certificate issuance with a verifiable identifier and completed curriculum version.

## Controlled-document integration

The module should support training linked to controlled documents, including required training, retraining after document revisions, and compliance evidence.

## Multilingual model

Programs, courses, modules and lessons should use Usina Docs native multilingual infrastructure. The initial AdvPL pilot prioritizes Brazilian Portuguese, English and Spanish.

## Architectural separation

The module should have an explicit code boundary, for example:

```text
app/Training/
resources/views/training/
routes/training.php
```

Expected dependency direction:

```text
Training ──► Editorial Core
Training ──► Users/Auth
Training ──► Translation
Training ──► Media
Training ──► Controlled Documents (optional)
```

The editorial core must not depend on Training.

## AdvPL Guide migration

Migration should be incremental: inventory existing courses, lessons, enrollments, progress, assessments and achievements; map them to native models; import representative content first; validate URLs, redirects, users and progress; run staging; preserve rollback; then migrate gradually.

Existing public AdvPL Guide URLs must not be broken.

## Suggested implementation phases

### Training 0 — Domain model and boundary
Programs, courses, tracks, modules, lessons, editorial-block integration and translations.

### Training 1 — Enrollment and progress
Enrollment, progress, resume behavior and basic indicators.

### Training 2 — Exercises and assessments
Versioned exercises, quizzes, attempts and error-guided review.

### Training 3 — Programs and competencies
Prerequisites, progression, completion projects and acquired competencies.

### Training 4 — Certificates
Eligibility rules, completion certificates, verifiable identifiers and completed curriculum version.

### Training 5 — Controlled training
Controlled-document links, mandatory training, renewal after revision/expiration, evidence and compliance reporting.

## Product direction

Training should not be treated as a collection of special pages. It is a **first-class domain module** built on Usina Docs services and designed for free courses, learning programs and controlled corporate training.