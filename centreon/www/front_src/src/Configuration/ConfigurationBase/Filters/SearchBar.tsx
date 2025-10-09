import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../translatedLabels';
import { useFilterStyles } from './Filters.styles';
import useSearch from './useSearch';

import AdvancedFilters from './AdvancedFilters';

const Filters = ({ filtersAtom, filtersAtomKey }): ReactElement => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const { filters, onChange, areAdvancedFiltersVisible } = useSearch({
    filtersAtom
  });

  const EndAdornment = useMemo(
    () => () => (
      <AdvancedFilters
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
