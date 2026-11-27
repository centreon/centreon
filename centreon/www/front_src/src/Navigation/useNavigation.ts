// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { getData, useRequest } from '@centreon/ui';

import { useAtom } from 'jotai';
import {
  always,
  append,
  cond,
  equals,
  filter,
  flatten,
  includes,
  isNil,
  map,
  mapObjIndexed,
  not,
  pipe,
  prop,
  propEq,
  T
} from 'ramda';
import { useCallback, useMemo } from 'react';

import Navigation, { Page } from './models';
import navigationAtom from './navigationAtoms';

export const navigationEndpoint =
  './api/internal.php?object=centreon_topology&action=navigationList';

interface UseNavigationState {
  allowedPages?: Array<string | Array<string>>;
  getNavigation: () => void;
  reactRoutes?: Record<string, string>;
}

const isDefined = pipe(isNil, not);
const propExists = <T>(property: string): ((obj: T) => boolean) =>
  pipe(prop(property) as (obj: T) => unknown, isDefined);

const getAllowedPages = ({ page, newAccumulator }): Array<string> => {
  return cond([
    [
      propEq(true, 'is_react') as (obj: Page) => boolean,
      always(append<string>(page.url as string, newAccumulator))
    ],
    [
      propExists<Page>('page'),
      always(append<string>(page.page as string, newAccumulator))
    ],
    [T, always(newAccumulator)]
  ])(page);
};

const useNavigation = (): UseNavigationState => {
  const { sendRequest } = useRequest<Navigation>({
    request: getData
  });
  const [navigation, setNavigation] = useAtom(navigationAtom);

  const getNavigation = (): void => {
    sendRequest({
      endpoint: navigationEndpoint
    }).then(setNavigation);
  };

  const reduceAllowedPages = useCallback(
    (acc: Array<string>, page: Page): Array<string> => {
      const children = pipe(
        map<string, string | null>((property) => {
          if (!page[property]) {
            return null;
          }

          return page[property].reduce(reduceAllowedPages, []);
        }),
        filter(isDefined)
      )(['groups', 'children']) as Array<string>;

      const newAccumulator = [...acc, ...flatten(children)];

      return getAllowedPages({ newAccumulator, page });
    },
    []
  );

  const findReactRoutes = useCallback(
    (acc, page: Page): Record<string, string> => {
      const children = mapObjIndexed((value, key) => {
        if (!includes(key, ['groups', 'children'])) {
          return null;
        }

        return (value as Array<Page>).reduce(findReactRoutes, {});
      }, page) as {
        children?: Record<string, string>;
        groups?: Record<string, string>;
      };

      const filteredChildren = filter(pipe(isNil, not), children);

      const newAccumulator = {
        ...acc,
        ...(filteredChildren?.children || {}),
        ...(filteredChildren?.groups || {})
      };

      if (equals(page.is_react, false) || isNil(page.url)) {
        return newAccumulator;
      }

      return {
        ...newAccumulator,
        [page.url as string]: page.page as string
      };
    },
    []
  );

  const allowedPages = useMemo(
    (): Array<string> | undefined =>
      isNil(navigation)
        ? undefined
        : (navigation.result || []).reduce(
            reduceAllowedPages,
            [] as Array<string>
          ),
    [navigation]
  );

  const reactRoutes = useMemo(
    (): Record<string, string> | undefined =>
      isNil(navigation)
        ? undefined
        : (navigation.result || []).reduce(findReactRoutes, {}),
    [navigation]
  );

  return {
    allowedPages,
    getNavigation,
    reactRoutes
  };
};

export default useNavigation;
