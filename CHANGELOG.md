<!--
 - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog for files_zip

## 2.2.0

- Add support for Nextcloud 32
- Update dependencies

## 2.1.0

### Added

- feat(deps): Add Nextcloud 31 support @nickvergessen [#275](https://github.com/nextcloud/files_zip/pull/275)
- ci: Update workflows @nickvergessen [#284](https://github.com/nextcloud/files_zip/pull/284)

### Fixed

- fix: disable zip on trashbin @skjnldsv [#282](https://github.com/nextcloud/files_zip/pull/282)
- fix(files): upgrade `vite-config` to `1.4.2` @JuliaKirschenheuter [#288](https://github.com/nextcloud/files_zip/pull/288)

### Other

- Chore(deps): Bump @nextcloud/vue from 8.15.0 to 8.15.1 @dependabot [#271](https://github.com/nextcloud/files_zip/pull/271)
- Chore(deps): Bump @nextcloud/files from 3.6.0 to 3.7.0 @dependabot [#272](https://github.com/nextcloud/files_zip/pull/272)
- Chore(deps): Bump @nextcloud/vue from 8.15.1 to 8.16.0 @dependabot [#273](https://github.com/nextcloud/files_zip/pull/273)
- Chore(deps): Bump @nextcloud/files from 3.7.0 to 3.8.0 @dependabot [#274](https://github.com/nextcloud/files_zip/pull/274)
- chore: update workflows from templates @nextcloud-command [#277](https://github.com/nextcloud/files_zip/pull/277)
- Chore(deps): Bump @nextcloud/vue from 8.16.0 to 8.17.0 @dependabot [#278](https://github.com/nextcloud/files_zip/pull/278)
- Chore(deps): Bump @nextcloud/dialogs from 5.3.5 to 5.3.7 @dependabot [#279](https://github.com/nextcloud/files_zip/pull/279)
- Chore(deps-dev): Bump nextcloud/coding-standard from 1.2.1 to 1.2.3 @dependabot [#280](https://github.com/nextcloud/files_zip/pull/280)
- Chore(deps): Bump @nextcloud/files from 3.8.0 to 3.9.0 @dependabot [#283](https://github.com/nextcloud/files_zip/pull/283)
- Chore(deps): Bump @nextcloud/vue from 8.17.0 to 8.17.1 @dependabot [#281](https://github.com/nextcloud/files_zip/pull/281)
- Chore(deps-dev): Bump nextcloud/coding-standard from 1.2.3 to 1.3.1 @dependabot [#287](https://github.com/nextcloud/files_zip/pull/287)

## 1.6.0

### Added

- feat(deps): Add Nextcloud 30 support @nickvergessen [#226](https://github.com/nextcloud/files_zip/pull/226)
- Add SPDX header @AndyScherzinger [#248](https://github.com/nextcloud/files_zip/pull/248)
- Remove duplicate license info @AndyScherzinger [#250](https://github.com/nextcloud/files_zip/pull/250)
- Migrate REUSE to TOML format @AndyScherzinger [#265](https://github.com/nextcloud/files_zip/pull/265)

## 1.5.0

### Added

- Nextcloud 28 compatibility
  - feat: Move to vue and @nextcloud/files actions @juliushaertl [#189](https://github.com/nextcloud/files_zip/pull/189)

### Fixed

- fix: Add share attribute check @juliushaertl [#192](https://github.com/nextcloud/files_zip/pull/192)
- fix(i18n):fixed typo @rakekniven [#191](https://github.com/nextcloud/files_zip/pull/191)
- (readme) Add usage info + tidy up headings @joshtrichards [#159](https://github.com/nextcloud/files_zip/pull/159)

## 1.4.0

### Removed

- Support for Nextcloud 22, 23 and 24

## 1.3.0 (not released)

### Added

- Compatibility with Nextcloud 27

### Fixed

- Fix button labels in dialog @danxuliu [#140](https://github.com/nextcloud/files_zip/pull/140)

### Changed

- Translation updates
- Dependency updates

## 1.2.0

### Added

- Add single file action for compressing to zip @juliushaertl [#86](https://github.com/nextcloud/files_zip/pull/86)
- Compatibility with Nextcloud 26

### Fixed

- Fix icon color inverting @juliushaertl [#111](https://github.com/nextcloud/files_zip/pull/111)

## 1.1.2

### Added

- Add option to limit the maximum amount of files in size [#79](https://github.com/nextcloud/files_zip/pull/79)

### Fixed

- Clean up any temp files after each job [#78](https://github.com/nextcloud/files_zip/pull/78)

## 1.1.1

### Fixed

- Propagate filesize change [#74](https://github.com/nextcloud/files_zip/pull/74)


## 1.1.0

- Nextcloud 24 compatibility
- Translation updates

## 1.0.1

### Fixed

- #40 Hide zip menu item when there is no create permission in a folder @juliushaertl
- #50 Add transifex config @nickvergessen
- #51 Add l10n/ folder @nickvergessen
- #47 Fix handling of invalid names @CarlSchwan
- #46 Fix notifications not getting translated @CarlSchwan
- #52 l10n: Correct spelling @Valdnet
- Bump dependencies
- Update translations


## 1.0.0

- Initial release
