<!--
 - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog for files_zip

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
