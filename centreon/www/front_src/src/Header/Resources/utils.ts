import numeral from 'numeral';

import type { Criteria } from '../../Resources/Filter/Criterias/models';

export const formatCount = (number: number | string): string =>
  numeral(number).format('0a');

export const formatUnhandledOverTotal = (
  unhandled: number | string,
  total: number | string
): string => `${formatCount(unhandled)}/${formatCount(total)}`;

type ChangeFilterAndNavigate = (params: {
  criterias: Array<Criteria>;
  link: string;
}) => (e: React.MouseEvent<HTMLLinkElement>) => void;

export const getNavigationFunction =
  ({ applyFilter, navigate, useDeprecatedPages }): ChangeFilterAndNavigate =>
  ({ link, criterias }) =>
  (e) => {
    e.preventDefault();

    if (!useDeprecatedPages) {
      applyFilter({ criterias, id: '', name: 'New Filter' });
    }

    navigate(link);
  };
