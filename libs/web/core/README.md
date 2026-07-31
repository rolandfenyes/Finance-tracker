# Web core

Application-wide API, session, route-policy, error, command, and exact-value
boundaries. Feature UI must consume the narrow exports from `src/index.ts` and
must not import generated-client internals through this library.
