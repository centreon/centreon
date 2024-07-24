import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../../translatedLabels';
import { useFilterStyles } from '../useActionsStyles';
import { filtersAtom } from '../../atom';
import useLoadData from '../../useLoadData';

import PopoverFilter from './PopoverFilter';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const { reload } = useLoadData();

  const onChange = (e): void => {
    setFilters({ ...filters, name: e.target.value });
  };

  const search = (event): void => {
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
      dataTestId={t(labelSearch)}
      placeholder={t(labelSearch)}
      value={filters?.name}
      onChange={onChange}
      onKeyDown={search}
    />
  );
};

export default Filters;
