import { KeyboardEvent, useEffect } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';
import { labelSearch } from '../../../translatedLabels';
import { currentFilterAtom } from '../Filter/atoms';
import useInitializeFilter from '../Filter/useInitializeFilter';
import { useActionsStyles } from '../actions.styles';

import Filters from '../Filter';
import { searchAtom } from './atoms';
import useBuildParameters from './useBuildParametrs';
import useSearch from './useSearch';

const TokenSearch = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();
  const [searchValue, setSearchValue] = useAtom(searchAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const { initialize } = useInitializeFilter();
  const { getSearchParameters } = useBuildParameters();

  useSearch();

  const clearFilters = (): void => {
    setSearchValue('');
    initialize();
  };

  const handleSearch = (e): void => {
    setSearchValue(e?.target?.value);
  };

  const sendRequest = (event: KeyboardEvent): void => {
    const enterKeyPressed = event.key === 'Enter';
    if (!enterKeyPressed) {
      return;
    }

    setCurrentFilter({
      ...currentFilter,
      search: getSearchParameters()
    });
  };

  useEffect(() => {
    if (searchValue) {
      return;
    }
    setCurrentFilter({ ...currentFilter, search: undefined });
  }, [searchValue]);

  return (
    <div className={classes.filters}>
      <SearchField
        debounced
        fullWidth
        EndAdornment={Filters}
        dataTestId={labelSearch}
        placeholder={t(labelSearch) as string}
        value={searchValue}
        onChange={handleSearch}
      />
    </div>
  );
};

export default TokenSearch;
