import { useState } from 'react';

import { useAtom } from 'jotai';

import { TextField, useDebounce, useMemoComponent } from '@centreon/ui';

import { SearchableFields } from '../../../Criterias/searchQueryLanguage/models';
import { searchAtom } from '../../../filterAtoms';
import useSearchInputDataByField from '../../useSearchInputDataByField';
import { replaceValueFromSearchInput } from '../../utils';

import useFilterSearchValue from './useFilterSearch';

const FilterSearch = ({
  field,
  placeholder
}: {
  field: SearchableFields;
  placeholder?: string;
}): JSX.Element => {
  const [isDirty, setIsDirty] = useState(false);
  const [search, setSearch] = useAtom(searchAtom);

  const { content, fieldInformation } = useSearchInputDataByField({ field });

  const { value, setValue } = useFilterSearchValue({
    content,
    isDirty,
    search
  });

  const debouncedRequest = useDebounce({
    functionToDebounce: (): void => {
      const isFieldExist = search.includes(field);

      if (!value) {
        setSearch(search.replace(fieldInformation, ''));

        return;
      }
      if (isFieldExist) {
        const updatedValue = replaceValueFromSearchInput({
          newContent: `${field}:${value}`,
          search,
          targetField: fieldInformation
        });
        setSearch(updatedValue);

        return;
      }
      const data = search.concat(' ', `${field}:${value}`);
      setSearch(data);
    },

    wait: 300
  });

  const onChange = (e) => {
    setValue(e.target.value);
    setIsDirty(true);

    debouncedRequest();
  };

  return useMemoComponent({
    Component: (
      <TextField
        dataTestId=""
        placeholder={placeholder}
        value={value}
        onChange={onChange}
      />
    ),
    memoProps: [search, value]
  });
};

export default FilterSearch;
