# Module loading baseline

The v1.4.8 registry reads each module's lightweight option before referencing its feature class.

| Frontend request | v1.4.7 feature files | v1.4.8 feature files |
| --- | ---: | ---: |
| All modules disabled | 16 | 0 |
| Only Performance & Cleanup enabled | 16 | 1 |

The isolated registry test records included files for both v1.4.8 scenarios. Shared bootstrap and storage classes remain autoloaded only when their callers need them. Existing option names, enable filters, admin URLs, and module classes remain compatible.

## WordPress runtime measurement

The all-disabled scenario was also measured on July 22, 2026 in the same local WordPress Studio site on PHP 8.3. Each result was reproduced across three requests after swapping only the plugin version.

| Version | Feature files | Memory usage | Peak memory | Database queries |
| --- | ---: | ---: | ---: | ---: |
| v1.4.7 | 16 | 59,171,832 bytes | 59,361,480 bytes | 4 |
| v1.4.8 | 0 | 56,842,472 bytes | 57,032,464 bytes | 4 |
| Difference | -16 | -2,329,360 bytes | -2,329,016 bytes | 0 |

With all modules disabled, v1.4.8 avoids loading every feature implementation and reduces request memory by about 2.22 MiB in this environment. The database query count is unchanged because WordPress loads these small module flags from its autoloaded options cache.
