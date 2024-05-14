import { ReactNode } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';
import { Variant } from '@mui/material/styles/createTypography';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../Criterias/models';

import { useStyles } from './basicFilter/checkBox/checkBox.style';
import {
  BasicCriteria,
  ChangedCriteriaParams,
  ExtendedCriteria
} from './model';
import useInputData from './useInputsData';

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

  const options = dataByFilterName?.options as Array<SelectEntry>;

  const transformData = (input: Array<SelectEntry>): Array<string> => {
    return input?.map((item) => item?.name);
  };

  const getTranslated = (values: Array<SelectEntry>): Array<SelectEntry> => {
    return values.map((entry) => ({
      id: entry.id,
      name: t(entry.name)
    }));
  };

  const translatedOptions = getTranslated(options);
  const translatedValues = getTranslated(
    dataByFilterName?.value as Array<SelectEntry>
  );

  const handleChangeStatus = (event): void => {
    const originalValue = translatedOptions.find(({ name }) =>
      equals(name, event.target.id)
    ) as SelectEntry;

    const item = options.find(({ id }) => equals(id, originalValue.id));

    if (event.target.checked) {
      const dataByFilterNameValue = dataByFilterName.value;

      changeCriteria({
        filterName,
        updatedValue: dataByFilterNameValue
          ? [...dataByFilterNameValue, item]
          : [item]
      });

      return;
    }
    const result = dataByFilterName.value.filter(
      ({ id }) => !equals(id, originalValue?.id)
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
