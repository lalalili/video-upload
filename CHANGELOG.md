# Changelog

All notable changes to `lalalili/video-upload` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- CI (PHP 8.3/8.4) and tag-triggered release workflows; baseline release
  discipline (pest + phpstan via `composer test` / `composer analyse`). CI checks
  out the `course-core` sibling so the `../course-core` path dependency resolves.

## [0.2.3]

- Reusable video upload session and provider status lifecycle for Laravel course
  applications (consumed by `aitehub`).
