import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

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
      dataTestId={t(labelSearch)}
      debounced
      fullWidth
      onChange={onChange}
      placeholder={t(labelSearch) as string}
    />
  );
};

export default Filter;
