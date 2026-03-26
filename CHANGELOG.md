# Changelog

## [1.1.0] - 2026-03-26

### Fixed
- Cache keys now include query parameters — requests to the same endpoint with different
  parameters (e.g. `?expand=comments`) are cached independently instead of returning
  the first cached variant
- Cache invalidation uses `TagDependency` to clear all parameter variants of a path at once
