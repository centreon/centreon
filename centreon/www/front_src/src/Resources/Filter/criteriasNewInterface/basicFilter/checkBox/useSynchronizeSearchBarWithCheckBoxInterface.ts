import { useEffect } from 'react';

import { equals, isEmpty, isNil } from 'ramda';

import { ResourceType } from '../../../../models';
import { Criteria, CriteriaDisplayProps } from '../../../Criterias/models';
import { SectionType, categoryHostStatus } from '../../model';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
  setSelectedStatusByResourceType;
}

const useSynchronizeSearchBarWithCheckBoxInterface = ({
  data,
  setSelectedStatusByResourceType,
  filterName,
  resourceType
}: Parameters): void => {
  useEffect(() => {
    const initialValue = data.find((item) =>
      equals(item.name, filterName)
    )?.value;

    if (isEmpty(initialValue) || isNil(initialValue)) {
      return () => undefined;
    }

    const result = initialValue.map((item) => {
      const type = Object.keys(categoryHostStatus).includes(item.id)
        ? ResourceType.host
        : resourceType;

      return { ...item, checked: true, resourceType: type };
    });

    setSelectedStatusByResourceType(result);

    return (): void => {
      setSelectedStatusByResourceType(null);
    };
  }, []);
};

export default useSynchronizeSearchBarWithCheckBoxInterface;
