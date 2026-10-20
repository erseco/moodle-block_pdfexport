# ADR-0003: Resource authorization

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

`download.php` receives `courseid`, `blockid` and `cmid` from the request. The alpha
checked block ownership, but kept a separate permission path for display and download
and could reveal content hidden through HTML attributes.

## Decision

Authorization is enforced in layers, recomputed on every request:

1. **Course**: `require_login($course)` (enrolment, guest access, site policies) and
   `block/pdfexport:view` in the course context; `export_service` re-checks
   `can_access_course()` and the capability itself (defence in depth).
2. **Block**: `publication::get_course_block()` loads the block by id and requires
   `blockname = pdfexport` and a parent context equal to the course context (not
   another course, the front page or a dashboard).
3. **Module**: the export list is rebuilt from `get_fast_modinfo()` for the current
   user, filtered by the block selection. A module is exportable only if it has an
   adapter, `cm_info::$uservisible` (visibility, availability, groups), its section is
   visible to the user, it is not being deleted, and the user has the module view
   capability (`mod/book:read` for books) when the module defines one.
4. **Content**: hidden book chapters require `mod/book:viewhiddenchapters`. Images
   resolve only to the exported module's own context, component, file area and item;
   anything else (other contexts, `..`, empty segments) is refused. No remote fetches.

Management requires `block/pdfexport:configure` and `moodle/course:update`, a session
key (Moodle Forms), and only ids listed on the page are saved.

Unpublished, hidden, restricted and nonexistent modules all yield the same
`notpublished` error, so existence is never disclosed.

## Alternatives considered

- Trusting the block's displayed list (computed for the page): stale, and URLs can be
  forged.
- A separate download capability: the view capability already expresses "can use the
  block"; module capabilities give the fine-grained control.

## Consequences

Admins can restrict exports per role with the standard capability system. Guests can
export only what they could already read in a course that allows guest access.

## Risks

A module type whose content is protected by a capability other than `mod/<name>:view`
needs its adapter to declare it (as the book adapter does).

## Validation

`export_service_test` (roles, hidden modules and sections, availability, groups,
capability overrides, foreign and unknown ids), `publication_test` (block
substitution), `adapters_test`, and the Behat refusal scenarios.
