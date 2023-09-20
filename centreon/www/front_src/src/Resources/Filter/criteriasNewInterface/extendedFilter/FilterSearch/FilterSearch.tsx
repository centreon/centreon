import { useMemo, useState } from 'react';

import { useAtom } from 'jotai';

import { TextField } from '@centreon/ui';

import { SearchableFields } from '../../../Criterias/searchQueryLanguage/models';
import { searchAtom } from '../../../filterAtoms';

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
      setSearch(search.replace(fieldData.content, value));

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

  const fieldData = useMemo((): string => {
    const target = search.split(' ').find((item) => item.includes(field));
    const fieldEntries = target?.split(':');
    const content = fieldEntries?.filter((item) => item !== field).join() ?? '';
    const data = { content, target };

    if (!search) {
      setValue('');

      return data;
    }

    return data;
  }, [field, search]);

  return (
    <div>
      <TextField
        dataTestId=""
        placeholder={placeHolder}
        value={!isDirty ? fieldData.content : value}
        onChange={onChange}
      />
    </div>
  );
};

export default FilterSearch;
