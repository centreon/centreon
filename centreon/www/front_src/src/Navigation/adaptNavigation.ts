import { chain, isEmpty, isNil, isNotNil } from 'ramda';

import type { RouteGroup, RouteHierarchy } from '../components/NavigationMenu';
import { getUrlFromEntry } from './getUrlFromEntry';
import icons from './icons';
import type { Page } from './models';

/** The group NavigationList.php puts the level 3 pages without a group in. */
export const defaultGroupLabel = 'Main Menu';

type Translate = (label: string) => string;

const isShown = (page: Page): boolean => page.show !== false;

const getChildPages = ({ children = [], groups = [] }: Page): Array<Page> => [
  ...children,
  ...chain((group) => group.children ?? [], groups)
];

const getPageRoutes = (page: Page): Array<string> => {
  const route = getUrlFromEntry(page);

  return [
    ...(isNil(route) ? [] : [route]),
    ...chain(getPageRoutes, getChildPages(page))
  ];
};

const getIcon = (page: Page): RouteHierarchy['Icon'] =>
  isNil(page.icon) ? undefined : icons[page.icon as keyof typeof icons];

const getBadge = (page: Page, translate: Translate): string | undefined =>
  page.is_react && typeof page.options === 'string' && !isEmpty(page.options)
    ? translate(page.options)
    : undefined;

const adaptPages = (
  pages: Array<Page> | undefined,
  translate: Translate
): Array<RouteHierarchy> =>
  (pages ?? [])
    .filter(isShown)
    .map((page) => adaptPage(page, translate))
    .filter(isNotNil);

const adaptGroups = (
  groups: Array<Page> | undefined,
  translate: Translate
): Array<RouteGroup> =>
  (groups ?? [])
    .map((group) => ({
      label:
        group.label === defaultGroupLabel ? undefined : translate(group.label),
      subRoutes: adaptPages(group.children, translate)
    }))
    .filter(({ subRoutes }) => !isEmpty(subRoutes));

function adaptPage(page: Page, translate: Translate): RouteHierarchy | null {
  const subRoutes = adaptPages(page.children, translate);
  const groups = adaptGroups(page.groups, translate);
  const firstChild = subRoutes[0] ?? groups[0]?.subRoutes[0];
  const route = firstChild?.route ?? getUrlFromEntry(page);

  if (isNil(route)) {
    return null;
  }

  const nonDisplayedRoutes = chain(
    getPageRoutes,
    getChildPages(page).filter((childPage) => !isShown(childPage))
  );
  const Icon = getIcon(page);
  const badge = getBadge(page, translate);

  return {
    label: translate(page.label),
    route,
    ...(Icon && { Icon }),
    ...(badge && { badge }),
    ...(isEmpty(subRoutes) ? {} : { subRoutes }),
    ...(isEmpty(groups) ? {} : { groups }),
    ...(isEmpty(nonDisplayedRoutes) ? {} : { nonDisplayedRoutes })
  };
}

/**
 * Maps the topology tree returned by the navigation endpoint (level 1
 * `children`, level 2 `groups`, level 3 `children`) to the menu hierarchy:
 * - hidden pages (`show: false`) become `nonDisplayedRoutes` of their parent,
 * - the default "Main Menu" group has no label, so no section header,
 * - empty groups and pages without any route are dropped,
 * - a parent leads to its first displayed leaf.
 */
export const adaptNavigation = (
  pages: Array<Page>,
  translate: Translate = (label) => label
): Array<RouteHierarchy> => adaptPages(pages, translate);
