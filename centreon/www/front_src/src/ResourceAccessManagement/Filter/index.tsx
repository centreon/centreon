import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { resourceAccessManagementSearchAtom } from '../atom';
import { labelSearch } from '../translatedLabels';

const Filter = (): JSX.Element => {
  const { t } = useTranslation();

  const setSearchValue = useSetAtom(resourceAccessManagementSearchAtom);

  const searchDebounced = debounce<(search: string) => void>(
    (debouncedSearch): void => {
      setSearchValue(debouncedSearch);
    },
    500
  );

  const onChange = ({ target }): void => {
    searchDebounced(target.value);
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
