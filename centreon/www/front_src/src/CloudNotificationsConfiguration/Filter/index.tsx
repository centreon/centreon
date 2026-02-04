import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';

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
      dataTestId={t(labelSearch)}
      debounced
      fullWidth
      onChange={onChange}
      placeholder={t(labelSearch) as string}
    />
  );
};

export default Filter;
