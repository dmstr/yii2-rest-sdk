# Changelog

## [1.2.0] - 2026-03-26

### Added
- `invalidateCache(string $path)` added to `HttpClientInterface`
- Hierarchical `TagDependency` tags — invalidating a parent path
  (e.g. `/posts`) also clears all child entries (e.g. `/posts/1`)
- Cache tests (15 tests covering keys, tags, invalidation, hierarchical cascading)

### Changed
- Mutating methods (POST, PATCH, DELETE) in `HttpClient` now automatically invalidate
  the cache for the request path
- `Resource::$cacheInvalidationPaths` now only handles cross-resource invalidation;
  per-path invalidation is handled by `HttpClient`
- Cache keys include query parameters — different parameter combinations are cached
  independently

### Fixed
- Requests to the same endpoint with different parameters (e.g. `?expand=comments`)
  no longer return the first cached variant

## [1.1.0] - 2026-03-26

### Fixed
- Cache keys now include query parameters
- Cache invalidation uses `TagDependency` to clear all parameter variants of a path at once
