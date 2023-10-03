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
    setTarget(item);

    const currentValueSearchData = findData({
      data: item?.searchData?.values,
      findBy: 'id',
      target: resourceType
    });
    setValueSearchData(currentValueSearchData);
  }, [data]);

  return { target, valueSearchData };
};

export default useInputData;
