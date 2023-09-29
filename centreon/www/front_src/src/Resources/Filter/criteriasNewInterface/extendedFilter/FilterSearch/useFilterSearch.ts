import { useState, useEffect } from 'react';

const useFilterSearchValue = ({ isDirty, content, search }) => {
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
