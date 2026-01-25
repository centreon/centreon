import { SearchField } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelSearch } from '../../../translatedLabels';
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
        dataTestId={'search-bar'}
        debounced
        EndAdornment={AdvancedFilters}
        fullWidth
        onChange={onChange}
        placeholder={t(labelSearch)}
        value={filters.name}
      />
    </div>
  );
};

export default Filters;
