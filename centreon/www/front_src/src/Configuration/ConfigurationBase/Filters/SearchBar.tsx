import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../translatedLabels';
import { useFilterStyles } from './Filters.styles';
import useSearch from './useSearch';

import AdvancedFilters from './AdvancedFilters';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const { filters, onChange, onSearch, areAdvancedFiltersVisible } =
    useSearch();

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        EndAdornment={areAdvancedFiltersVisible && AdvancedFilters}
        dataTestId={'search-bar'}
        placeholder={t(labelSearch)}
        value={filters.name}
        onChange={onChange}
        onKeyDown={onSearch}
      />
    </div>
  );
};

export default Filters;
