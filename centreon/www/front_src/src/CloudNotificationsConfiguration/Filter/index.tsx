import { useRef } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { searchAtom } from '../atom';
import { labelSearch } from '../translatedLabels';

const Filter = (): JSX.Element => {
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
      dataTestId={t(labelSearch)}
      placeholder={t(labelSearch) as string}
      onChange={onChange}
    />
  );
};

export default Filter;
