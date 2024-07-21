import { useRef } from 'react';

import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { searchAtom } from '../../atom';
import { labelSearch } from '../../../translatedLabels';
import { useFilterStyles } from '../useActionsStyles';

import PopoverFilter from './PopoverFilter';

const Filters = (): JSX.Element => {
  const { classes } = useFilterStyles();
  const { t } = useTranslation();

  const setSearchValue = useSetAtom(searchAtom);

  const searchDebounced = useRef(
    debounce<(search) => void>((debouncedSearch): void => {
      setSearchValue(debouncedSearch);
    }, 500)
  );

  const onChange = ({ target }): void => {
    searchDebounced.current(target.value);
  };

  return (
    <SearchField
      debounced
      fullWidth
      EndAdornment={PopoverFilter}
      className={classes.filters}
      dataTestId={t(labelSearch)}
      placeholder={t(labelSearch)}
      onChange={onChange}
    />
  );
};

export default Filters;
