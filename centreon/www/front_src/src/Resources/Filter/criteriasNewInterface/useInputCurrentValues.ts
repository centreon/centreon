import { Dispatch, SetStateAction, useEffect, useState } from 'react';

interface Parameters {
  content: unknown;
  data: unknown;
}

interface UseInputCurrentValue {
  setValue: Dispatch<SetStateAction<[] | unknown>>;
  value: unknown | [];
}

const useInputCurrentValues = ({
  data,
  content
}: Parameters): UseInputCurrentValue => {
  const [value, setValue] = useState<unknown | []>([]);

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
