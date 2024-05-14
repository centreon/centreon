import { ReactNode } from 'react';

import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Variant } from '@mui/material/styles/createTypography';
import { Typography } from '@mui/material';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../Criterias/models';

import { useStyles } from './basicFilter/checkBox/checkBox.style';
import {
  BasicCriteria,
  ChangedCriteriaParams,
  ExtendedCriteria
} from './model';
import useInputData from './useInputsData';
import { findData } from './utils';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: BasicCriteria | ExtendedCriteria;
  title?: ReactNode;
}

export const CheckBoxWrapper = ({
  title,
  data,
  filterName,
  changeCriteria
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

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

  const getTranslated = (values: Array<SelectEntry>): Array<SelectEntry> => {
    return values.map((entry) => ({
      id: entry.id,
      name: t(entry.name)
    }));
  };

  const translatedOptions = getTranslated(
    dataByFilterName?.options as Array<SelectEntry>
  );
  const translatedValues = getTranslated(
    dataByFilterName?.value as Array<SelectEntry>
  );

  const handleChangeStatus = (event): void => {
    const originalValue = translatedOptions.find(({ name }) =>
      equals(name, event.target.id)
    );
    const item = findData({
      data: dataByFilterName?.options,
      filterName: originalValue?.id,
      findBy: 'id'
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
      (v) => v.id !== originalValue?.id
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
        options={transformData(translatedOptions)}
        values={transformData(translatedValues)}
        onChange={(event) => handleChangeStatus(event)}
      />
    </>
  );
};
