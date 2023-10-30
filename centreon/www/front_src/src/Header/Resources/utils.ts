import {
  formatTopCounterCount,
  formatTopCounterUnhandledOverTotal
} from '@centreon/ui';

import type { Criteria } from '../../Resources/Filter/Criterias/models';

export const formatCount = formatTopCounterCount;
export const formatUnhandledOverTotal = formatTopCounterUnhandledOverTotal;

type ChangeFilterAndNavigate = (params: {
  criterias: Array<Criteria>;
  link: string;
}) => (e: React.MouseEvent<HTMLLinkElement>) => void;

export const getNavigationFunction =
  ({ applyFilter, navigate, useDeprecatedPages }): ChangeFilterAndNavigate =>
  ({ link, criterias }) =>
  (e) => {
    e.preventDefault();

    navigate(link);

    if (!useDeprecatedPages) {
      applyFilter({ criterias, id: '', name: 'New Filter' });
    }
  };
