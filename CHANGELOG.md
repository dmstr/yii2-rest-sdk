# Changelog

## [1.1.1] - 2026-03-26

### Changed
- Mutating methods (POST, PATCH, DELETE) in `HttpClient` now automatically invalidate
  the cache for the request path
- Cache entries use hierarchical `TagDependency` tags — invalidating a parent path
  (e.g. `/posts`) also clears all child entries (e.g. `/posts/1`)
- `Resource::$cacheInvalidationPaths` now only handles cross-resource invalidation;
  per-path invalidation is handled by `HttpClient`

## [1.1.0] - 2026-03-26

### Fixed
- Cache keys now include query parameters — requests to the same endpoint with different
  parameters (e.g. `?expand=comments`) are cached independently instead of returning
  the first cached variant
- Cache invalidation uses `TagDependency` to clear all parameter variants of a path at once
