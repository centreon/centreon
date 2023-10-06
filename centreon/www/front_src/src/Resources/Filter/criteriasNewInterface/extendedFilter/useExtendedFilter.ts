import { useEffect, useState } from 'react';

import { findData, sort } from '../utils';
import { ExtendedCriteria } from '../model';

const useExtendedFilter = ({ data }) => {
  const [resourceTypes, setResourceTypes] = useState();
  const [statusTypes, setStatusTypes] = useState();
  const [inputGroupsData, setInputGroupsData] = useState();
  useEffect(() => {
    if (!data) {
      return;
    }

    const types = findData({ data, target: ExtendedCriteria.resourceTypes });
    setResourceTypes(types?.options);

    const status = data?.filter(
      (item) => item.name === ExtendedCriteria.statusTypes
    );
    setStatusTypes(status);

    const arrayInputGroup = data?.filter(
      (item) => item?.buildAutocompleteEndpoint
    );
    const inputGroups = sort({
      array: arrayInputGroup,
      sortBy: 'name'
    })?.filter((item) => !item.name.includes('level'));
    setInputGroupsData(inputGroups);
  }, [data]);

  return { inputGroupsData, resourceTypes, statusTypes };
};

export default useExtendedFilter;
