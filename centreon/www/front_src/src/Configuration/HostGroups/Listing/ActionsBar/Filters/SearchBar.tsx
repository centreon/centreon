import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../../translatedLabels';
import { filtersAtom } from '../../atom';
import useLoadData from '../../useLoadData';
import { useFilterStyles } from '../useActionsStyles';

import PopoverFilter from './PopoverFilter';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const { reload } = useLoadData();

  const onChange = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const onSearch = (event): void => {
    const enterKeyPressed = equals(event.key, 'Enter');
    if (!enterKeyPressed) {
      return;
    }

    reload();
  };

  return (
    <SearchField
      debounced
      fullWidth
      EndAdornment={PopoverFilter}
      className={classes.filters}
      dataTestId={labelSearch}
      placeholder={t(labelSearch)}
      value={filters.name}
      onChange={onChange}
      onKeyDown={onSearch}
    />
  );
};

export default Filters;
