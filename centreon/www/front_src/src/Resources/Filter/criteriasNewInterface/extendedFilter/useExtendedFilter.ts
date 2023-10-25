import { useEffect, useState } from 'react';

import { SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { ExtendedCriteria } from '../model';
import { findData, sortByNameCaseInsensitive } from '../utils';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
}

interface UseExtendedFilter {
  inputGroupsData?: Array<Criteria & CriteriaDisplayProps>;
  resourceTypes?: Array<SelectEntry>;
  statusTypes?: Array<Criteria & CriteriaDisplayProps>;
}

const useExtendedFilter = ({ data }: Parameters): UseExtendedFilter => {
  const [resourceTypes, setResourceTypes] = useState<Array<SelectEntry>>();
  const [statusTypes, setStatusTypes] =
    useState<Array<Criteria & CriteriaDisplayProps>>();
  const [inputGroupsData, setInputGroupsData] =
    useState<Array<Criteria & CriteriaDisplayProps>>();
  useEffect(() => {
    if (!data) {
      return;
    }

    const types = findData({
      data,
      filterName: ExtendedCriteria.resourceTypes
    });
    setResourceTypes(types?.options);

    const status = data?.filter(
      (item) => item.name === ExtendedCriteria.statusTypes
    );
    setStatusTypes(status);

    const arrayInputGroup = data?.filter(
      (item) => item?.buildAutocompleteEndpoint
    );
    const inputGroups = sortByNameCaseInsensitive(arrayInputGroup)?.filter(
      (item) => !item.name.includes('level')
    );
    setInputGroupsData(inputGroups);
  }, [data]);

  return { inputGroupsData, resourceTypes, statusTypes };
};

export default useExtendedFilter;
