import { includes, isEmpty } from 'ramda';
import useNavigation from 'www/front_src/src/Navigation/useNavigation';

export const useSearchPages = (search) => {
  const { menu } = useNavigation();

  const reduceParentPage = ({ pages }) => {
    if (!pages) {
      return [];
    }
    return pages.reduce((acc, page) => {
      if (!page.show) {
        return acc;
      }
      const pageState =
        page.url || page.page
          ? {
              url: page.is_react ? page.url : `/main.php?p=${page.page}`,
              label: page.label
            }
          : undefined;

      if (!isEmpty(page.children)) {
        return [
          ...acc,
          ...reduceParentPage({ pages: page.children }),
          pageState
        ];
      }

      if (!isEmpty(page.groups)) {
        return [...acc, ...reduceParentPage({ pages: page.group }), pageState];
      }

      return [...acc, pageState];
    }, []);
  };

  const searchablePages = reduceParentPage({ pages: menu }).filter((v) => v);

  const searchedPages = searchablePages.filter(({ label }) =>
    includes(search.toLowerCase(), label.toLowerCase())
  );

  return searchedPages.slice(0, 5);
};
