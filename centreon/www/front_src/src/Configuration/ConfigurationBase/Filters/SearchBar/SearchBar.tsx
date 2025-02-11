import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';
import PopoverFilter from '../PopoverFilter';
import useSearch from './useSearch';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const { filters, onChange, onSearch } = useSearch();

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        EndAdornment={PopoverFilter}
        dataTestId={labelSearch}
        placeholder={t(labelSearch)}
        value={filters.name}
        onChange={onChange}
        onKeyDown={onSearch}
      />
    </div>
  );
};

export default Filters;
