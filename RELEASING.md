# Releasing

Releases are driven by the commit messages on `main`. Nobody edits a version
number by hand.

## How it works

1. Every merge to `main` updates a release pull request, opened by
   release-please. It works out the next version from the Conventional Commits
   since the last release, writes `CHANGELOG.md` and bumps the `VERSION`
   constant in `src/Client.php`.
2. Merging that pull request tags `vX.Y.Z` and publishes a GitHub Release.
3. Packagist picks the tag up through its GitHub App, so there is nothing to
   upload and no token anywhere.
4. The release also triggers `publish.yml`, which waits for the checks on that
   exact commit, checks the tag against the `VERSION` constant, validates the
   composer manifest, runs the style, static analysis and test suites again,
   and attaches the archive to the release.

`feat:` gives a minor bump, `fix:` a patch, and a `!` or a `BREAKING CHANGE:`
footer a major one. Before 1.0.0 a breaking change bumps the minor.

## What a human has to do once

- Submit `checkslip/sdk` to Packagist and install its GitHub App, so new tags
  appear without anyone pushing a button.
- Allow GitHub Actions to create pull requests in the repository settings, or
  release-please cannot open its release pull request.

`composer.json` deliberately carries no `version` field: Packagist takes the
version from the tag, and a hand-written one only goes stale.
