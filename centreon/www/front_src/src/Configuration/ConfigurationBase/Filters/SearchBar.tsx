import { SearchField } from '@centreon/ui';

import { PrimitiveAtom } from 'jotai';
import { ReactElement, useMemo } from 'react';
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
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const { filters, onChange, areAdvancedFiltersVisible } = useSearch<TFilters>({
    filtersAtom
  });

  const EndAdornment = useMemo(
    () => () => (
      <AdvancedFilters<TFilters>
        areAdvancedFiltersVisible={areAdvancedFiltersVisible}
        filtersAtom={filtersAtom}
        filtersAtomKey={filtersAtomKey}
      />
    ),
    [areAdvancedFiltersVisible, filtersAtom]
  );

  return (
    <div className={classes.filters}>
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
