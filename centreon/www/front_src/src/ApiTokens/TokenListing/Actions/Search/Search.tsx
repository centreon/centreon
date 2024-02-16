import { KeyboardEvent, useEffect, useRef } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { renderEndAdornmentFilter } from '../../../../Resources/Filter';
import { labelSearch } from '../../../translatedLabels';
import { useStyles } from '../actions.styles';
import { currentFilterAtom } from '../Filter/atoms';
import useInitializeFilter from '../Filter/useInitializeFilter';

import { searchAtom } from './atoms';
import useSearch from './useSearch';
import { buildSearchParameters } from './utils';

const TokenSearch = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const searchRef = useRef<HTMLDivElement | null>(null);
  const [searchValue, setSearchValue] = useAtom(searchAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const { initialize } = useInitializeFilter();

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
      search: buildSearchParameters(searchValue)()
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
        inputProps={{ 'data-testid': 'search' }}
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
