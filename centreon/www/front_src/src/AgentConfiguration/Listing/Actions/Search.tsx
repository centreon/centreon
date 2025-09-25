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
        debounced
        fullWidth
        dataTestId={labelSearch}
        placeholder={t(labelSearch)}
        onChange={onChange}
        EndAdornment={PopoverFilter}
        value={filters.name}
      />
    </div>
  );
};

export default Search;
