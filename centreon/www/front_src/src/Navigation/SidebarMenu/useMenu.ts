import { equals, isEmpty, isNil } from 'ramda';
import { useCallback, useMemo } from 'react';
import { useLocation } from 'react-router';

import { Page } from '../models';
import { searchUrlFromEntry } from '../Sidebar/helpers/getUrlFromEntry';

export const getPageKey = (page: Page): string =>
  `${page.label}-${page.page ?? page.url ?? ''}`;

export const getGroups = (page: Page): Array<Page> =>
  (page.groups ?? []).filter(
    (group) => !isNil(group.children) && !isEmpty(group.children)
  );

export const hasGroups = (page: Page): boolean => !isEmpty(getGroups(page));

const getDescendants = (page: Page): Array<Page> => [
  ...(page.children ?? []),
  ...getGroups(page).flatMap((group) => group.children ?? [])
];

export const getPageUrl = (page: Page): string =>
  searchUrlFromEntry(page) ?? '';

export const getBadge = (page: Page): string | null =>
  page.is_react && typeof page.options === 'string' && !isEmpty(page.options)
    ? page.options
    : null;

interface UseMenuResult {
  isBranchSelected: (page: Page) => boolean;
  isSelected: (page: Page) => boolean;
}

export const useMenu = (): UseMenuResult => {
  const { pathname, search } = useLocation();

  const currentLegacyPage = useMemo(
    () =>
      pathname.includes('main.php')
        ? new URLSearchParams(search).get('p')
        : null,
    [pathname, search]
  );

  const isSelected = useCallback(
    (page: Page): boolean => {
      if (page.is_react) {
        if (isNil(page.url)) {
          return false;
        }

        return (
          equals(pathname, page.url) || pathname.startsWith(`${page.url}/`)
        );
      }

      if (isNil(currentLegacyPage) || isNil(page.page)) {
        return false;
      }

      // Level 4 legacy pages (7 digits) belong to their level 3 parent (5 digits).
      return (
        equals(currentLegacyPage, page.page) ||
        (equals(page.page.length, 5) && currentLegacyPage.startsWith(page.page))
      );
    },
    [pathname, currentLegacyPage]
  );

  const isBranchSelected = useCallback(
    (page: Page): boolean =>
      isSelected(page) || getDescendants(page).some(isBranchSelected),
    [isSelected]
  );

  return { isBranchSelected, isSelected };
};

export interface ActiveTrail {
  sectionKey: string;
  subKey: string | null;
}

// Section (level 1) and level 2 entry holding the current page.
export const findActiveTrail = (
  sections: Array<Page>,
  isBranchSelected: (page: Page) => boolean
): ActiveTrail | null => {
  const section = sections.find(isBranchSelected);

  if (isNil(section)) {
    return null;
  }

  const sub = (section.children ?? []).find(
    (child) => hasGroups(child) && isBranchSelected(child)
  );

  return {
    sectionKey: getPageKey(section),
    subKey: isNil(sub) ? null : getPageKey(sub)
  };
};
