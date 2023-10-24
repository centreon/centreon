import { useEffect, useState } from 'react';

import { SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { ExtendedCriteria } from '../model';
import { sortByNameCaseInsensitive } from '../utils';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
}

interface UseExtendedFilter {
  inputGroupsData?: Array<Criteria & CriteriaDisplayProps>;
  resourceTypes?: Array<SelectEntry>;
  statusTypes?: Array<Criteria & CriteriaDisplayProps>;
}

const useExtendedFilter = ({ data }: Parameters): UseExtendedFilter => {
  const [statusTypes, setStatusTypes] =
    useState<Array<Criteria & CriteriaDisplayProps>>();
  const [inputGroupsData, setInputGroupsData] =
    useState<Array<Criteria & CriteriaDisplayProps>>();

  useEffect(() => {
    if (!data) {
      return;
    }

    const status = data?.filter(
      (item) => item.name === ExtendedCriteria.statusTypes
    );
    setStatusTypes(status);

    const arrayInputGroup = data?.filter(
      (item) => item?.buildAutocompleteEndpoint
    );
    const sortedInputGroups = sortByNameCaseInsensitive(
      arrayInputGroup
    )?.filter((item) => !item.name.includes('level'));

    setInputGroupsData(sortedInputGroups);
  }, [data]);

  return { inputGroupsData, statusTypes };
};

export default useExtendedFilter;
