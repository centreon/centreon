import { useEffect, useState } from 'react';

import { findData } from './utils';

const useInputData = ({ data, filterName }) => {
  const [options, setOptions] = useState();
  const [values, setValues] = useState();
  const [label, setLabel] = useState();
  const [target, setTarget] = useState();

  useEffect(() => {
    if (!data) {
      return;
    }
    const item = findData({ data, target: filterName });
    setOptions(item?.options);
    setValues(item?.value);
    setLabel(item?.label);
    setTarget(item);
  }, [data]);

  return { label, options, target, values };
};

export default useInputData;
