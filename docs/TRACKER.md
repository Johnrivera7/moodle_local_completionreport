# Reporte para Moodle Tracker — MDL-XXXXX (borrador)

Use este texto al crear un issue en https://tracker.moodle.org

---

## Summary

Activity completion report (`report/progress`) displays activities in wrong order on courses using **subsections** (Moodle 4.5+ / 5.x). Activities from visually last sections appear first because ordering follows internal `section` numbers, not visual course page order.

## Component/s

- Activity completion
- report_progress
- mod_subsection (related)

## Affects Version/s

- 4.5, 5.0, 5.1, 5.2 (verified on 5.2 live site)

## Description

When a course uses subsections inside early modules, delegated sections receive high internal section numbers (e.g. 27–30) while closing sections may have lower numbers (e.g. 7–8).

`completion_info::get_activities()` uses `course_modinfo::get_cms()` which orders by internal section sequence. The progress report helper `report_progress\local\helper::get_activities_to_show()` only offers "Order in course" and "Alphabetical", but "Order in course" does not match the visual order shown in the course index / course page when subsections are present.

### Steps to reproduce

1. Create a course with custom sections format and subsections enabled.
2. Add section "0. Start" with subsections containing completion-tracked activities.
3. Add intermediate modules (sections 1–5) with completion-tracked activities.
4. Add closing sections "Cierre" and "Preparación" (may be hidden) with completion-tracked activities.
5. Open **Reports → Activity completion** with "Activity order: Order in course".
6. Compare column order with course page / course index sidebar.

### Expected behaviour

First columns should match first completion-tracked activities in "0. Start" (visual order top to bottom).

### Actual behaviour

First columns show activities from closing sections (e.g. "¿QUÉ SE VIENE AHORA?" from "Cierre") while activities from the initial subsections appear at the end of the horizontal table.

### Live example (anonymised)

- Site: production Moodle 5.2+ (Chilean education platform)
- Course id: 1239 ("Pre Escuela 2027")
- 109 completion-tracked activities, 111 table columns
- First visible columns: Cierre + Preparándome sections
- Last columns: ¿Qué es la Pre Escuela?, Programa de Liderazgo, Experiencia Enseña Chile, Construyamos red (inside "0. Antes de partir")

Internal section numbers from filter dropdown:

| Visual position | Section name | Internal section # |
|-----------------|--------------|-------------------|
| End of course | Cierre Pre Escuela | 7 |
| End of course | Preparándome para la Escuela de Verano | 8 |
| Start (subsection) | ¿Qué es la Pre Escuela? | 27 |
| Start (subsection) | Programa de Liderazgo | 28 |
| Start (subsection) | Experiencia Enseña Chile | 29 |
| Start (subsection) | Construyamos red | 30 |

### Technical analysis

- `report/progress/classes/local/helper.php` → `get_activities_to_show()` calls `$completion->get_activities()`.
- `completion_info::get_activities()` iterates `$modinfo->get_cms()` ordered by internal appearance index.
- Subsection delegated sections are assigned section numbers that do not reflect nesting under parent sections on the course page.
- Section filter added in MDL-73503 (4.2) filters by `sectionnum` but does not fix global ordering.

### Suggested fix

When building activity list for "orderincourse", traverse visual structure:

1. `$modinfo->get_listed_section_info_all()`
2. For each section, iterate `$modinfo->sections[$section->section]`
3. When encountering `mod_subsection`, recurse into `$cm->get_delegated_section_info()`
4. Collect only completion-tracked activities in that traversal order

Reference implementation: `local_completionreport\classes\activity_order.php` (community plugin).

## Workaround

- Filter by section in the core report (partial — subsections listed separately).
- Use local plugin `local_completionreport` for correct visual order.

## Priority

Minor/Major — report is usable but misleading for course coordinators monitoring sequential progress.

---

*Prepared: 2026-09-01 — John Rivera*
