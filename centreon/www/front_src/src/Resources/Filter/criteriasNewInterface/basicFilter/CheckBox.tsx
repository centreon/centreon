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
  const { target } = useInputData({
    data,
    filterName
  });

  const transformData = (input: array<{ id: string; name: string }>): any =>
    input?.map((item) => item?.name);

  const handleChangeStatus = (event) => {
    const item = findData({ data: target?.options, target: event.target.id });

    if (event.target.checked) {
      changeCriteria({
        filterName,
        updatedValue: [...target?.value, item]
      });

      return;
    }
    const result = target?.value?.filter((v) => v.name !== event.target.id);
    changeCriteria({
      filterName,
      updatedValue: result
    });
  };

  return (
    <div>
      {title}
      {target?.options && (
        <CheckboxGroup
          direction="horizontal"
          options={transformData(target?.options)}
          values={transformData(target?.value) ?? []}
          onChange={(event) => handleChangeStatus(event)}
        />
      )}
    </div>
  );
};
