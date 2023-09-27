import { useEffect, useState } from 'react';

import { findData } from './utils';

const useInputData = ({ data, filterName, resourceType }) => {
  const [target, setTarget] = useState();
  const [valueSearchData, setValueSearchData] = useState();

  useEffect(() => {
    if (!data) {
      return;
    }
    const item = findData({ data, target: filterName });

    const currentValueSearchData = item?.searchData?.values?.find(
      (item) => item?.id === resourceType
    );
    setValueSearchData(currentValueSearchData);
    setTarget(item);
  }, [data]);

  return { target, valueSearchData };
};

export default useInputData;
