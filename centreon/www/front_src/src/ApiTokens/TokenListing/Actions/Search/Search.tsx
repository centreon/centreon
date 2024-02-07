import { KeyboardEvent } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { renderEndAdornmentFilter } from '../../../../Resources/Filter';
import { labelSearch } from '../../../translatedLabels';
import { useStyles } from '../actions.styles';

import { currentFilterAtom } from './Filter/atoms';
import { searchAtom } from './atoms';
import useBuildSearchParameters from './useSearch';

const TokenSearch = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [searchValue, setSearchValue] = useAtom(searchAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const { getSearchParameters } = useBuildSearchParameters(searchValue);

  const clearFilters = (): void => {};

  const handleSearch = (e): void => {
    setSearchValue(e?.target?.value);
  };

  const sendRequest = (event: KeyboardEvent): void => {
    const enterKeyPressed = event.key === 'Enter';
    if (!enterKeyPressed) {
      return;
    }

    setCurrentFilter({ ...currentFilter, search: getSearchParameters() });
  };

  return (
    <div className={classes.search}>
      <SearchField
        fullWidth
        EndAdornment={renderEndAdornmentFilter(clearFilters)}
        dataTestId={labelSearch}
        placeholder={t(labelSearch) as string}
        value={searchValue}
        onChange={handleSearch}
        onKeyDown={sendRequest}
      />
    </div>
  );
};

export default TokenSearch;
