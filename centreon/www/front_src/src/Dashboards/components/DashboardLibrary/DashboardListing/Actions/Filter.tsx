import { useRef, useState } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import CloseIcon from '@mui/icons-material/Close';
import debounce from '@mui/utils/debounce';

import { IconButton, SearchField } from '@centreon/ui';

import { searchAtom } from '../atom';
import { labelClearFilter, labelSearch } from '../translatedLabels';

import { useActionsStyles } from './useActionsStyles';

export const renderEndAdornmentFilter = (onClear) => (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={t(labelClearFilter) as string}
      data-testid={labelClearFilter}
      size="small"
      title={t(labelClearFilter) as string}
      onClick={onClear}
    >
      <CloseIcon color="action" fontSize="small" />
    </IconButton>
  );
};

const Filter = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  /* eslint-disable hooks/sort */
  const [searchValue, setSearchValue] = useAtom(searchAtom);
  const [inputValue, setInputValue] = useState(searchValue);
  /* eslint-enable hooks/sort */

  const searchDebounced = useRef(
    debounce<(debouncedSearch: string) => void>((debouncedSearch): void => {
      setSearchValue(debouncedSearch);
    }, 500)
  );

  const onChange = ({ target }): void => {
    setInputValue(target.value);
    searchDebounced.current(target.value);
  };

  const clearFilter = (): void => {
    setInputValue('');
    setSearchValue('');
  };

  return (
    <SearchField
      debounced
      fullWidth
      EndAdornment={renderEndAdornmentFilter(clearFilter)}
      className={classes.filter}
      dataTestId={t(labelSearch)}
      placeholder={t(labelSearch)}
      value={inputValue}
      onChange={onChange}
    />
  );
};

export default Filter;
