import { KeyboardEvent, useEffect, useRef } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { renderEndAdornmentFilter } from '../../../../Resources/Filter';
import { labelSearch } from '../../../translatedLabels';
import { currentFilterAtom } from '../Filter/atoms';
import useInitializeFilter from '../Filter/useInitializeFilter';
import { useStyles } from '../actions.styles';

import { searchAtom } from './atoms';
import useBuildParameters from './useBuildParametrs';
import useSearch from './useSearch';

const TokenSearch = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const searchRef = useRef<HTMLDivElement | null>(null);
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
    <div className={classes.search}>
      <SearchField
        fullWidth
        EndAdornment={renderEndAdornmentFilter(clearFilters)}
        autoComplete="off"
        dataTestId={labelSearch}
        id="searchBar"
        inputProps={{ 'data-testid': 'inputSearch' }}
        inputRef={searchRef}
        placeholder={t(labelSearch) as string}
        value={searchValue}
        onChange={handleSearch}
        onKeyDown={sendRequest}
      />
    </div>
  );
};

export default TokenSearch;
