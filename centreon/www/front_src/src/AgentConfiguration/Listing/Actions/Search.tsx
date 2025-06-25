import { JSX } from 'react';

import { SearchField } from '@centreon/ui';
import { useTranslation } from 'react-i18next';
import { labelSearch } from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import Filters from './PopoverFilter';
import { useSearch } from './useSearch';

const Search = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { filters, change } = useSearch();

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        dataTestId={labelSearch}
        placeholder={t(labelSearch)}
        onChange={change}
        EndAdornment={Filters}
        value={filters.name}
      />
    </div>
  );
};

export default Search;
