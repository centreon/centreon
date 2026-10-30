// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { SearchField } from '@centreon/ui';

import { PrimitiveAtom } from 'jotai';
import { ReactElement, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { labelSearch } from '../translatedLabels';
import AdvancedFilters from './AdvancedFilters';
import { useFilterStyles } from './Filters.styles';
import useSearch from './useSearch';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
}

const Filters = <TFilters,>({
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): ReactElement => {
  const { classes } = useFilterStyles({});
  const { t } = useTranslation();
  const searchFieldRef = useRef<HTMLDivElement>(null);

  const { filters, onChange, areAdvancedFiltersVisible } = useSearch<TFilters>({
    filtersAtom
  });

  const EndAdornment = useMemo(
    () => () => (
      <AdvancedFilters<TFilters>
        areAdvancedFiltersVisible={areAdvancedFiltersVisible}
        filtersAtom={filtersAtom}
        filtersAtomKey={filtersAtomKey}
        searchFieldRef={searchFieldRef}
      />
    ),
    [areAdvancedFiltersVisible, filtersAtom]
  );

  return (
    <div className={classes.filters} ref={searchFieldRef}>
      <SearchField
        dataTestId={'search-bar'}
        debounced
        EndAdornment={EndAdornment}
        fullWidth
        onChange={onChange}
        placeholder={t(labelSearch)}
        value={filters.name}
      />
    </div>
  );
};

export default Filters;
