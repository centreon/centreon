import {
  append,
  equals,
  filter,
  flatten,
  includes,
  isEmpty,
  isNil,
  map,
  pipe
} from 'ramda';
import { useCallback, useMemo } from 'react';
import useNavigation, {
  isDefined
} from 'www/front_src/src/Navigation/useNavigation';

export const useSearchPages = (search) => {
  const { menu } = useNavigation();

  const reduceAllowedPages = useCallback(
    (parent) =>
      (acc: Array<string>, page): Array<string> => {
        const children = pipe(
          map<string, string | null>((property) => {
            if (!page[property]) {
              return null;
            }

            return page[property].reduce(
              reduceAllowedPages(
                equals(page.show, undefined) ? parent : page.label
              ),
              []
            );
          }),
          filter(isDefined)
        )(['groups', 'children']) as Array<string>;

        const newAccumulator = [...acc, ...flatten(children)];

        const newPage =
          (page.is_react || page.page) &&
          !page.children &&
          (!page.groups || isEmpty(page.groups))
            ? {
                label: page.label,
                parentLabel: parent,
                url: page.is_react ? page.url : `/main.php?p=${page.page}`
              }
            : undefined;

        return append(newPage, newAccumulator);
      },
    []
  );

  const searchablePages = useMemo(
    (): Array<string> =>
      isNil(menu)
        ? []
        : (menu || [])
            .reduce(reduceAllowedPages(null), [] as Array<string>)
            .filter((v) => v),
    [menu]
  );

  const searchedPages = searchablePages.filter(({ label }) =>
    includes(search.toLowerCase(), label.toLowerCase())
  );

  return searchedPages.slice(0, 5).map(({ label, parentLabel, url }) => ({
    label: `Go to "${parentLabel} > ${label}" page`,
    url
  }));
};
