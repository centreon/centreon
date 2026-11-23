import { chain, isNil } from 'ramda';

import type { RouteHierarchy } from './models';

export const getChildRoutes = ({
  subRoutes = [],
  groups = []
}: RouteHierarchy): Array<RouteHierarchy> => [
  ...subRoutes,
  ...chain((group) => group.subRoutes, groups)
];

export const hasChildRoutes = (entry: RouteHierarchy): boolean =>
  getChildRoutes(entry).length > 0;

/**
 * Every route reachable from the given entries: their own routes, their
 * non-displayed routes and those of all their descendants.
 */
export const getRoutesFromHierarchy = (
  routes: Array<RouteHierarchy>
): Array<string> =>
  chain(
    (entry) => [
      entry.route,
      ...(entry.nonDisplayedRoutes ?? []),
      ...getRoutesFromHierarchy(getChildRoutes(entry))
    ],
    routes
  );

export const containsRoute = (entry: RouteHierarchy, route?: string): boolean =>
  !isNil(route) && getRoutesFromHierarchy([entry]).includes(route);
