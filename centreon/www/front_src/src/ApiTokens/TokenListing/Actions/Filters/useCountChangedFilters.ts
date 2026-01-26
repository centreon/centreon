import { useAtomValue } from 'jotai';
import { equals, filter, length, pipe, toPairs } from 'ramda';

import { filtersAtom } from '../../atoms';
import { filtersInitialValues } from '../../utils';

const countDifferences = (defaultValues, values) =>
  pipe(
    toPairs,
    filter(([key, val]) => !equals(val, values[key])),
    length
  )(defaultValues);

interface Props {
  changedFiltersCount: number;
  isClear: boolean;
}

const useCountChangedFilters = (): Props => {
  const filters = useAtomValue(filtersAtom);

  const changedFiltersCount = countDifferences(filtersInitialValues, filters);

  return {
    changedFiltersCount,
    isClear: equals(changedFiltersCount, 0)
  };
};

export default useCountChangedFilters;
