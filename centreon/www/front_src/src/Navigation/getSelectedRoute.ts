import { isNil } from 'ramda';

import {
  getRoutesFromHierarchy,
  type RouteHierarchy
} from '../components/NavigationMenu';

const legacyPath = '/main.php';

const getPageParameter = (search: string): string | null =>
  new URLSearchParams(search).get('p');

const isLegacyRoute = (route: string): boolean =>
  route.startsWith(`${legacyPath}?`);

interface Props {
  pathname: string;
  routes: Array<RouteHierarchy>;
  search: string;
}

/**
 * Finds the menu route matching the current location:
 * - legacy pages (`/main.php?p=…`) by their topology page number,
 * - React pages by path, the longest one prefixing the location otherwise
 *   (a dashboard highlights the dashboards library).
 */
export const getSelectedRoute = ({
  pathname,
  routes,
  search
}: Props): string | undefined => {
  const menuRoutes = getRoutesFromHierarchy(routes);

  if (pathname.endsWith(legacyPath)) {
    const page = getPageParameter(search);

    return isNil(page)
      ? undefined
      : menuRoutes.find(
          (route) =>
            isLegacyRoute(route) &&
            getPageParameter(route.slice(legacyPath.length)) === page
        );
  }

  const reactRoutes = menuRoutes.filter((route) => !isLegacyRoute(route));

  return (
    reactRoutes.find((route) => route === pathname) ??
    reactRoutes
      .filter((route) => pathname.startsWith(`${route}/`))
      .sort((a, b) => b.length - a.length)[0]
  );
};
