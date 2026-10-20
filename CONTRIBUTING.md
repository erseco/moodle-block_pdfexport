# Contributing

Thanks for helping with `block_pdfexport`.

## Reporting issues

Open an issue at https://github.com/erseco/moodle-block_pdfexport/issues with the
Moodle and PHP versions, the steps to reproduce, and what you expected. Report security
problems privately through GitHub's "Report a vulnerability" form, not in a public
issue.

## Pull requests

1. Branch from `main` (`feature/<topic>` or `hotfix/<topic>`).
2. Follow [AGENTS.md](AGENTS.md): Moodle coding style, permissions checked at export
   time, translated strings in English and Spanish, no remote fetches.
3. Add tests: PHPUnit for logic (including the security edge cases), Behat for UI
   workflows. Keep coverage at 90% or more.
4. Run `make lint test coverage behat plugin-ci` (see [DEVELOPMENT.md](DEVELOPMENT.md)).
5. Add an entry under *Unreleased* in [CHANGELOG.md](CHANGELOG.md).
6. Use Conventional Commit messages (`feat:`, `fix:`, `docs:`, `test:`, `ci:`).

CI must be green before review. The pull request gets a "Preview in Moodle
Playground" link to try the change in the browser.

## Licence

Contributions are accepted under the GNU GPL v3 or later, like the plugin.
