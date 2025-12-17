import { SearchField } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelSearch } from '../translatedLabels';
import { useStyles } from './Filters.styles';
import AdvancedFilters from './PopoverFilter';
import useSearch from './useSearch';

const Filters = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { filters, onChange } = useSearch();

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        EndAdornment={AdvancedFilters}
        dataTestId={'search-bar'}
        placeholder={t(labelSearch)}
        value={filters.name}
        onChange={onChange}
      />
    </div>
  );
};

export default Filters;
