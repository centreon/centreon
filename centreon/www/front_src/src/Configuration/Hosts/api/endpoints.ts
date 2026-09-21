/**
 * Hosts are served by API Platform at a bare `./api`, not at `./api/latest`.
 *
 * Legacy routes are mounted under `/api/{version}` (`config/routes/centreon.yaml`),
 * API Platform under a bare `/api`. `LegacyApiPrefixAliasLoader` re-exposes an
 * allowlisted subset of API Platform operations under `/api/latest` for
 * backward compatibility — `_api_/configuration/hosts_get_collection` is not on
 * that allowlist, and `FindHostsRoute.yaml` still answers there with the legacy
 * `{ result, meta }` envelope. Calling `./api` directly reaches the API
 * Platform resource we actually want.
 *
 * This base applies to the LISTING ONLY. API Platform exposes just `Post` and
 * `GetCollection` for hosts — there is no single-host operation. `GetHost`,
 * `PartialUpdateHost` and `DeleteHost` exist only as legacy routes under
 * `/api/{version}`, so any future `getOne`/`update`/`delete` endpoint must keep
 * the default `./api/latest` base rather than inherit this one.
 */
export const hostsBaseEndpoint = './api';

export const hostsListEndpoint = '/configuration/hosts';
