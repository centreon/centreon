import { useMemo, useState } from 'react';

import { useAtom } from 'jotai';

import { TextField } from '@centreon/ui';

import { SearchableFields } from '../../../Criterias/searchQueryLanguage/models';
import { searchAtom } from '../../../filterAtoms';
import {
  findFieldInformationFromSearchInput,
  replaceValueFromSearchInput
} from '../../utils';

import useDebounce from './useDebounce';

const FilterSearch = ({
  field,
  placeHolder
}: {
  field: SearchableFields;
}): JSX.Element => {
  const [value, setValue] = useState('');
  const [isDirty, setIsDirty] = useState(false);
  const [search, setSearch] = useAtom(searchAtom);

  const debouncedRequest = useDebounce(() => {
    const isFieldExist = search.includes(field);

    if (!value) {
      setSearch(search.replace(fieldData.target, ''));

      return;
    }
    if (isFieldExist) {
      const updatedValue = replaceValueFromSearchInput({
        newContent: `${field}:${value}`,
        search,
        targetField: fieldData.target
      });
      setSearch(updatedValue);

      return;
    }
    const data = search.concat(' ', `${field}:${value}`);
    setSearch(data);
  });

  const onChange = (e) => {
    setValue(e.target.value);
    setIsDirty(true);

    debouncedRequest();
  };

  const fieldData = useMemo((): { content: string; target: string } => {
    const data = findFieldInformationFromSearchInput({ field, search });
    if (!search) {
      setValue('');

      return data;
    }

    return data;
  }, [field, search]);

  return (
    <TextField
      dataTestId=""
      placeholder={placeHolder}
      value={value}
      value={!isDirty ? fieldData.content : value}
      onChange={onChange}
    />
  );
};

export default FilterSearch;
