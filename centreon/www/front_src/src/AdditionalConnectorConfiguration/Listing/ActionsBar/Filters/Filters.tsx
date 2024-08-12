import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../../translatedLabels';
import { useFilterStyles } from '../useActionsStyles';
import { searchAtom } from '../../atom';
import useLoadData from '../../useLoadData';

import PopoverFilter from './PopoverFilter';
import useUpdateFiltersBasedOnSearchBar from './useUpdateFiltersBasedOnSearchBar';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const [search, setSearch] = useAtom(searchAtom);

  const { reload } = useLoadData();

  useUpdateFiltersBasedOnSearchBar();

  const onChange = (e): void => {
    setSearch(e.target.value);
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
      value={search}
      onChange={onChange}
      onKeyDown={onSearch}
    />
  );
};

export default Filters;
