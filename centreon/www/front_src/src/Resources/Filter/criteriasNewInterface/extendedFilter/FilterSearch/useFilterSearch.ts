import { Dispatch, SetStateAction, useEffect, useState } from 'react';

interface Params {
  content: string;
  isDirty: boolean;
  search: string;
}
interface UseFilterSearchValue {
  setValue: Dispatch<SetStateAction<string>>;
  value: string;
}

const useFilterSearchValue = ({
  isDirty,
  content,
  search
}: Params): UseFilterSearchValue => {
  const [value, setValue] = useState('');

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

  return { setValue, value };
};

export default useFilterSearchValue;
