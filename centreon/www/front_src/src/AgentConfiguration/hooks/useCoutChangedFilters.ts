import { useAtomValue } from 'jotai';
import { equals, filter, length, pipe, toPairs } from 'ramda';
import { filtersAtom } from '../atoms';
import { filtersDefaultValue } from '../utils';

const countDifferences = (defaultValues, values) =>
  pipe(
    toPairs,
    filter(([key, val]) => !equals(val, values[key])),
    length
  )(defaultValues);

interface Props {
  isClear: boolean;
  changedFiltersCount: number;
}

const useCoutChangedFilters = (): Props => {
  const filters = useAtomValue(filtersAtom);

  const changedFiltersCount = countDifferences(filtersDefaultValue, filters);

  return {
    isClear: equals(changedFiltersCount, 0),
    changedFiltersCount
  };
};

export default useCoutChangedFilters;
