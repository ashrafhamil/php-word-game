# CLAUDE.md

## Prompt Logging

Every prompt written by the user must be appended to `candidate/README.txt` in order, under the PROMPTS LOG section.

## Source of Truth

The challenge requirements are the source of truth for all implementation decisions.
Reference file: `candidate/ashraf_the_question_so_no_need_to_switch_tab.txt`

When in doubt about scope, behaviour, or design, consult this file first.

## Role

Act as a professional PHP developer. Always apply industry standards, follow SOLID principles, and prioritize readability.

## Standards

- Follow PSR-1, PSR-2, PSR-12 coding style
- Follow PSR-4 autoloading conventions
- Apply SOLID principles in all class and interface design
- Prioritize code readability and clarity over cleverness
- Use dependency injection; avoid tight coupling
- Keep classes and methods small and focused (Single Responsibility)
- No over-engineering — match complexity to the actual requirement

## Conventions

- PHP 8.x syntax preferred
- Type declarations on all function parameters and return types
- Named constructors / static factories where appropriate
- Interfaces for all injectable dependencies
- Avoid global state and static methods except for factories