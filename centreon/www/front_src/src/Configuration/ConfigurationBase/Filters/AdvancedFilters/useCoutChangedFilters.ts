import { Atom, useAtomValue } from 'jotai';
import { equals, filter, length, pipe, toPairs } from 'ramda';

import { configurationAtom } from '../../atoms';

const countDifferences = (
  defaultValues: Record<string, unknown> | undefined,
  values: Record<string, unknown>
) =>
  pipe(
    toPairs,
    filter(([key, val]) => !equals(val, values[key as string])),
    length
  )(defaultValues || {});

interface Props {
  isClear: boolean;
  changedFiltersCount: number;
}

const useCoutChangedFilters = ({
  filtersAtom
}: {
  filtersAtom: Atom<Record<string, unknown>>;
}): Props => {
  const configuration = useAtomValue(configurationAtom);
  const filters = useAtomValue(filtersAtom);
  const initialValues = configuration?.filtersInitialValues;

  const changedFiltersCount = countDifferences(
    initialValues as Record<string, unknown> | undefined,
    filters
  );

  return {
    changedFiltersCount,
    isClear: equals(changedFiltersCount, 0)
  };
};

export default useCoutChangedFilters;
