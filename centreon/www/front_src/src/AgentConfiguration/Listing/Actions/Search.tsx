import { SearchField } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelSearch } from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import PopoverFilter from './PopoverFilter';
import { useSearch } from './useSearch';

const Search = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { onChange, filters } = useSearch();

  return (
    <div className={classes.search}>
      <SearchField
        dataTestId={labelSearch}
        debounced
        EndAdornment={PopoverFilter}
        fullWidth
        onChange={onChange}
        placeholder={t(labelSearch)}
        value={filters.name}
      />
    </div>
  );
};

export default Search;
