/* eslint-disable consistent-return */
import { useEffect, useState } from 'react';

import { isNil, isEmpty } from 'ramda';

import { CriteriaDisplayProps, Criteria } from '../../../Criterias/models';
import { ResourceType } from '../../../../models';
import {
  ChangedCriteriaParams,
  SectionType,
  SelectedResourceType,
  categoryHostStatus
} from '../../model';

interface Parameters {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
  selectedStatusByResourceType: Array<SelectedResourceType> | null;
  setSelectedStatusByResourceType;
}

interface UseCheckBox {
  values: Array<SelectedResourceType> | [];
}

const useCheckBox = ({
  data,
  selectedStatusByResourceType,
  setSelectedStatusByResourceType,
  filterName,
  resourceType
}: Parameters): UseCheckBox => {
  const [values, setValues] = useState<Array<SelectedResourceType>>([]);

  useEffect(() => {
    if (!selectedStatusByResourceType) {
      setValues([]);

      return;
    }

    const checkedValues = selectedStatusByResourceType?.filter(
      (item) => item.checked && item.resourceType === resourceType
    );
    setValues(checkedValues);
  }, [selectedStatusByResourceType]);

  useEffect(() => {
    const initialValue = data.find((item) => item.name === filterName)?.value;

    if (isEmpty(initialValue) || isNil(initialValue)) {
      return;
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

  return { values };
};

export default useCheckBox;
