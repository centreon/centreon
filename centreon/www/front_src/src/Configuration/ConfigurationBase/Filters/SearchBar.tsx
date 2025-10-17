import { PrimitiveAtom } from 'jotai';
import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../translatedLabels';
import { useFilterStyles } from './Filters.styles';
import useSearch from './useSearch';

import AdvancedFilters from './AdvancedFilters';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
}

const Filters = <TFilters,>({
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): ReactElement => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const { filters, onChange, areAdvancedFiltersVisible } = useSearch<TFilters>({
    filtersAtom
  });

  const EndAdornment = useMemo(
    () => () => (
      <AdvancedFilters<TFilters>
        filtersAtomKey={filtersAtomKey}
        filtersAtom={filtersAtom}
        areAdvancedFiltersVisible={areAdvancedFiltersVisible}
      />
    ),
    [areAdvancedFiltersVisible, filtersAtom]
  );

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        EndAdornment={EndAdornment}
        dataTestId={'search-bar'}
        placeholder={t(labelSearch)}
        value={filters.name}
        onChange={onChange}
      />
    </div>
  );
};

export default Filters;
