# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

- `Added` for new features.
- `Changed` for changes in existing functionality.
- `Deprecated` for soon-to-be removed features.
- `Removed` for now removed features.
- `Fixed` for any bug fixes.
- `Security` in case of vulnerabilities

## [1.1.2] - 2021.02.24

### Changed

- Updated vendor libraries.
- Updated related resource response from `400` to `404`.
- Updated `ApiController` constructor to allow/disallow checking of the `Accept` HTTP headers.
- Removed the protocol from the JWT iss claim.
- Updated `Users` controller to require global permissions to update user status.

## [1.1.1] - 2021.02.16

### Changed

- Moved protected `_userCan` method from `Users` controller to `ApiController`.

## [1.1.0] - 2021.02.15

### Added

- Added routes, controllers and schemas to be used with the `BonesAuth` service.

## [1.0.1] - 2021.01.19

### Changed

- Updated vendor dependencies.
- Updated HTTP status code to return `200` instead of `201` on successful authentication.
- Renamed `Webhooks` controller to `PublicController` and added a public "API status" route.

## [1.0.0] - 2020.12.05

### Added

- Initial release.