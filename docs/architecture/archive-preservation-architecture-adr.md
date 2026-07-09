# ADR-001: Archive Preservation Architecture

## Status

Draft

---

# Purpose

The purpose of this architecture is to preserve archival integrity while
providing sustainable public access through the simplest maintainable
architecture.

The goal is not to build a general-purpose Digital Asset Management system.

The preservation archive is expected to outlive the software used to access it.

---

# Design Goals

The architecture is guided by the following priorities, in order.

## 1. KISS: Keep It Simple

Simplicity is the primary architectural requirement.

Every additional component increases maintenance, documentation, testing, and
bus factor.

Complexity may only be introduced when repeated operational experience
demonstrates that it is necessary.

The system should solve today's problems using the smallest number of concepts
that preserves long-term integrity.

---

## 2. Preserve Provenance

Every public representation must be traceable to one preservation master or a
documented set of preservation masters.

Breaking this relationship is considered an archival error.

---

## 3. Low Bus Factor

The archive should remain understandable by a technically competent newcomer
within one working day.

The system should favor obvious structure over clever implementation.

---

## 4. CMS Independence

Applications, databases, and websites are replaceable.

The archive is not.

---

## 5. Predictable Long-Term Cost

Growth of the archive should primarily increase preservation storage rather
than monthly operational costs.

---

## 6. Graceful Degradation

Failure is expected.

The system should continue to make sense even when individual components
disappear.

---

# Architectural Principles

## The Archive Owns The Truth

The preservation archive is the authoritative source.

Original master files and preservation metadata belong to the archive.

No public application owns preservation assets.

---

## Public Systems Are Projections

Public systems consume projections of the archive.

They do not define the archive.

Public representations may always be regenerated from preservation data.

---

## Preserve Before Publishing

Preservation and publication are separate concerns.

Publication should never dictate preservation strategy.

---

## Explicit Is Better Than Implicit

The architecture should prefer visible files, explicit relationships, and
documented processes over hidden application state.

---

## Prefer Guardrails Over Discipline

The correct workflow should be the easiest workflow.

The system should reduce opportunities for provenance loss rather than relying
on users to avoid mistakes.

---

# System Boundaries

The architecture consists of independent layers.

```text
Preservation Archive
  |
Standalone Builder
  |
Projection Snapshot
  |
Archivist Workbench
  |
Public Projection
  |
Website
```

Each layer has one responsibility.

Replacing one layer should not require redesigning the others.

---

# Preservation Model

The preservation archive contains:

* preservation packages
* master files
* manifests
* integrity information
* preservation metadata

The preservation archive exists independently of any CMS or database.

The filesystem is the final line of defense.

A preservation package should remain understandable without the software that
created it.

A directory should explain itself.

---

# Projection Model

Public systems consume projection snapshots.

A projection snapshot is an immutable export produced by one builder run.

It represents the public projection for a defined scope at one point in time.

Projection snapshots may always be discarded and regenerated.

They are operational artifacts, not preservation assets.

---

# Builder Philosophy

The builder is an offline preservation tool.

It should:

* produce deterministic results;
* be safe to rerun;
* have no hidden state;
* generate understandable logs;
* never modify preservation packages.

Every projection must be reproducible from:

* preservation packages;
* documented configuration;
* builder version.

Nothing else.

---

# Archivist Workbench

Archivists should work through a simple local workbench rather than command-line
tools.

The workbench is an operational convenience.

It is not part of the preservation archive.

Removing the workbench must never compromise preservation.

Review decisions made in the workbench must be exportable and inspectable
outside the workbench.

---

# Failure Philosophy

The architecture assumes that software will eventually disappear.

Examples include:

* WordPress
* databases
* hosting providers
* builder implementations
* workbench applications

The preservation archive should remain understandable and recoverable.

The architecture prefers explicit failure over silent corruption.

If provenance cannot be established, the system should report uncertainty
rather than invent certainty.

---

# Conceptual Model

The architecture intentionally uses only a small number of concepts.

* Collection
* Preservation Package
* Object
* Derivative
* Projection

Everything else is an implementation detail.

---

# Consequences

Advantages:

* strong provenance
* low bus factor
* CMS independence
* predictable operating costs
* simple mental model
* straightforward disaster recovery
* gradual evolution without redesign

Trade-offs:

* preservation and publication are separate workflows
* some public representations require regeneration
* operational convenience is occasionally sacrificed for archival integrity

---

# Deferred Decisions

The following topics are intentionally outside the scope of this ADR and may be
addressed by future architecture decisions:

* preservation package format;
* metadata embedding strategy;
* projection snapshot format;
* storage backend;
* cold storage implementation;
* IIIF support;
* OpenSeadragon integration;
* OCR indexing;
* derivative generation strategy.

These decisions must respect the principles defined in this document rather
than redefine them.

---

# ADR Sequence

This is ADR-001. Later archive preservation decisions are subordinate to it.

* ADR-002: Preservation Package Format
* ADR-003: Builder Architecture
* ADR-004: Projection Snapshot Format
* ADR-005: WordPress Integration
* ADR-006: Metadata And Provenance Strategy
