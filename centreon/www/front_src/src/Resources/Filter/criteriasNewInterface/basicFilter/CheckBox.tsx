import { ReactNode, useEffect, useState } from 'react';

import { useAtom } from 'jotai';

import { CheckboxGroup } from '@centreon/ui';

import useInputData from '../useInputsData';
import { findData, removeDuplicateFromObjectArray } from '../utils';

import { selectedStatusByResourceTypeAtom } from './atoms';

interface Props {
  changeCriteria;
  data;
  filterName;
  title?: ReactNode;
}

export const CheckBoxSection = ({
  data,
  filterName,
  changeCriteria,
  resourceType
}: Props): JSX.Element => {
  const [values, setValues] = useState();
  const [selectedStatusByResourceType, setSelectedStatusByResourceType] =
    useAtom(selectedStatusByResourceTypeAtom);
  const { target } = useInputData({
    data,
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
      const value = { ...item, checked: true, resourceType };

      const res = removeDuplicateFromObjectArray({
        array: selectedStatusByResourceType
          ? [...selectedStatusByResourceType, value]
          : [value],
        byFields: ['id', 'resourceType']
      });
      setSelectedStatusByResourceType(res);

      changeCriteria({
        filterName,
        updatedValue: res.filter((item) => item.checked)
      });

      return;
    }

    const res = removeDuplicateFromObjectArray({
      array: [
        ...selectedStatusByResourceType,
        { ...item, checked: false, resourceType }
      ],
      byFields: ['id', 'resourceType']
    });

    setSelectedStatusByResourceType(res);
    changeCriteria({
      filterName,
      updatedValue: res.filter((item) => item.checked)
    });
  };

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

  return (
    <div>
      {target?.options && (
        <CheckboxGroup
          direction="horizontal"
          options={transformData(target?.options)}
          values={transformData(values) ?? []}
          onChange={(event) => handleChangeStatus(event)}
        />
      )}
    </div>
  );
};
