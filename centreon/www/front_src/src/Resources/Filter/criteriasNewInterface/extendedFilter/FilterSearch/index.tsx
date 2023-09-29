import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';

import { TextField, useDebounce } from '@centreon/ui';

import { SearchableFields } from '../../../Criterias/searchQueryLanguage/models';
import { searchAtom } from '../../../filterAtoms';
import useSearchInputDataByField from '../../useSearchInputDataByField';
import { replaceValueFromSearchInput } from '../../utils';

const FilterSearch = ({
  field,
  placeHolder
}: {
  field: SearchableFields;
}): JSX.Element => {
  const [value, setValue] = useState('');
  const [isDirty, setIsDirty] = useState(false);
  const [search, setSearch] = useAtom(searchAtom);

  const { content, fieldInformation } = useSearchInputDataByField({ field });

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

  // a deplacer vers specific hoooooook
  useEffect(() => {
    if (!isDirty) {
      setValue(content);

      return;
    }
    if (search) {
      return;
    }
    setValue('');
  }, [search, isDirty]);

  return (
    <TextField
      dataTestId=""
      placeholder={placeHolder}
      value={value}
      onChange={onChange}
    />
  );
};

export default FilterSearch;
