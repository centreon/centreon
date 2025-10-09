import { useAtomValue } from 'jotai';
import { equals, filter, length, pipe, toPairs } from 'ramda';
import { configurationAtom } from '../../atoms';

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

const useCoutChangedFilters = ({ filtersAtom }): Props => {
  const configuration = useAtomValue(configurationAtom);
  const filters = useAtomValue(filtersAtom);
  const initialValues = configuration?.filtersInitialValues;

  const changedFiltersCount = countDifferences(initialValues, filters);

  return {
    isClear: equals(changedFiltersCount, 0),
    changedFiltersCount
  };
};

export default useCoutChangedFilters;
