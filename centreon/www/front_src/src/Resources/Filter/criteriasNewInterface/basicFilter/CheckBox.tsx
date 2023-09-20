import { ReactNode } from 'react';

import { CheckboxGroup } from '@centreon/ui';

import useInputData from '../useInputsData';
import { findData } from '../utils';

interface Props {
  changeCriteria;
  data;
  filterName;
  title?: ReactNode;
}

export const CheckBoxWrapper = ({
  title,
  data,
  filterName,
  changeCriteria
}: Props): JSX.Element => {
  const { options, values } = useInputData({
    data,
    filterName
  });

  const transformData = (input: array<{ id: string; name: string }>): any =>
    input?.map((item) => item?.name);

  const handleChangeStatus = (event) => {
    const item = findData({ data: options, target: event.target.id });

    if (event.target.checked) {
      changeCriteria({
        filterName,
        updatedValue: [...values, item]
      });

      return;
    }
    const result = values?.filter((v) => v.name !== event.target.id);
    changeCriteria({
      filterName,
      updatedValue: result
    });
  };

  return (
    <>
      {title}
      {options && (
        <CheckboxGroup
          direction="horizontal"
          options={transformData(options)}
          values={transformData(values) ?? []}
          onChange={(event) => handleChangeStatus(event)}
        />
      )}
    </>
  );
};
