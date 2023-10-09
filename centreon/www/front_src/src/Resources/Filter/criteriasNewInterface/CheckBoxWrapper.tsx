import { ReactNode } from 'react';

import { Variant } from '@mui/material/styles/createTypography';
import { Typography } from '@mui/material';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../Criterias/models';

import { useStyles } from './basicFilter/checkBox/checkBox.style';
import { ChangedCriteriaParams } from './model';
import useInputData from './useInputsData';
import { findData } from './utils';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  title?: ReactNode;
}

export const CheckBoxWrapper = ({
  title,
  data,
  filterName,
  changeCriteria
}: Props): JSX.Element | null => {
  const { classes } = useStyles();

  const labelProps = {
    classes: { root: classes.label },
    variant: 'body2' as Variant
  };
  const formGroupProps = { classes: { root: classes.container } };
  const { dataByFilterName } = useInputData({
    data,
    filterName
  });

  if (!dataByFilterName) {
    return null;
  }

  const transformData = (input: Array<SelectEntry>): Array<string> => {
    return input?.map((item) => item?.name);
  };

  const handleChangeStatus = (event): void => {
    const item = findData({
      data: dataByFilterName?.options,
      filterName: event.target.id
    });

    if (event.target.checked) {
      const dataByFilterNameValue = dataByFilterName?.value;
      changeCriteria({
        filterName,
        updatedValue: dataByFilterNameValue
          ? [...dataByFilterNameValue, item]
          : [item]
      });

      return;
    }
    const result = dataByFilterName?.value?.filter(
      (v) => v.name !== event.target.id
    );
    changeCriteria({
      filterName,
      updatedValue: result
    });
  };

  return (
    <>
      <Typography classes={{ root: classes.title }} variant="subtitle2">
        {title}
      </Typography>

      <CheckboxGroup
        className={classes.checkbox}
        direction="horizontal"
        formGroupProps={formGroupProps}
        labelProps={labelProps}
        options={transformData(dataByFilterName.options)}
        values={transformData(dataByFilterName.value)}
        onChange={(event) => handleChangeStatus(event)}
      />
    </>
  );
};
