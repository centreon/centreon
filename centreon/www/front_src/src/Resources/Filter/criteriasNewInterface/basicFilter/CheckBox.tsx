import { ReactNode, useEffect, useMemo, useState } from 'react';

import { useAtom } from 'jotai';

import { CheckboxGroup } from '@centreon/ui';

import useInputData from '../useInputsData';
import { findData, removeDuplicateFromObjectArray } from '../utils';

import { selectedStatusByResourceTypeAtom } from './atoms';
import useSectionsData from './sections/useSections';

interface Props {
  changeCriteria;
  data;
  filterName;
  resourceType;
}

const CheckBoxSection = ({
  data,
  filterName,
  changeCriteria,
  resourceType
}: Props): JSX.Element => {
  const [values, setValues] = useState();

  const [selectedStatusByResourceType, setSelectedStatusByResourceType] =
    useAtom(selectedStatusByResourceTypeAtom);
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });
  const { target } = useInputData({
    data: sectionData,
    filterName
  });

  const transformData = (input: Array<{ id: string; name: string }>): any => {
    return input?.map((item) => item?.name);
  };

  const handleChangeStatus = (event) => {
    const item = findData({
      data: target?.options,
      target: event.target.id
    });

    if (event.target.checked) {
      const currentValue = { ...item, checked: true, resourceType };
      const result = removeDuplicateFromObjectArray({
        array: selectedStatusByResourceType
          ? [...selectedStatusByResourceType, currentValue]
          : [currentValue],
        byFields: ['id', 'resourceType']
      });
      setSelectedStatusByResourceType(result);

      return;
    }

    const result = removeDuplicateFromObjectArray({
      array: [
        ...selectedStatusByResourceType,
        { ...item, checked: false, resourceType }
      ],
      byFields: ['id', 'resourceType']
    });
    setSelectedStatusByResourceType(result);
  };

  useEffect(() => {
    if (!selectedStatusByResourceType) {
      setValues([]);

      return;
    }
    changeCriteria({
      filterName,
      updatedValue: selectedStatusByResourceType?.filter(
        (item) => item?.checked
      )
    });

    const checkedValues = selectedStatusByResourceType?.filter(
      (item) => item.checked && item.resourceType === resourceType
    );
    setValues(checkedValues);
  }, [selectedStatusByResourceType]);

  return (
    <CheckboxGroup
      direction="horizontal"
      options={transformData(target?.options) ?? []}
      values={transformData(values) ?? []}
      onChange={(event) => handleChangeStatus(event)}
    />
  );
};

export default CheckBoxSection;
