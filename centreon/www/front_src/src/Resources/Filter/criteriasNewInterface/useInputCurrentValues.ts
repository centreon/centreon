import { useEffect, useState } from 'react';

const useInputCurrentValues = ({ data, content }) => {
  const [value, setValue] = useState([]);
  useEffect(() => {
    if (!data) {
      setValue([]);

      return;
    }
    setValue(content);
  }, [data]);

  return { setValue, value };
};

export default useInputCurrentValues;
