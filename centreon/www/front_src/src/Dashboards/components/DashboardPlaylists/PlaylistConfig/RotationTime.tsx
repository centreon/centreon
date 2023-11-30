import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import WatchIcon from '@mui/icons-material/Watch';
import { Typography } from '@mui/material';

import {
  InputPropsWithoutGroup,
  TextField,
  usePluralizedTranslation
} from '@centreon/ui';

import { labelRotationTime, labelSecond } from '../../../translatedLabels';

import { usePlaylistConfigStyles } from './PlaylistConfig.styles';

const RotationTime = ({ fieldName }: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = usePlaylistConfigStyles();

  const { pluralizedT } = usePluralizedTranslation();
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const value = values[fieldName];

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      fieldName,
      equals(event.target.value, '') || Number(event.target.value) < 0
        ? 1
        : Number(event.target.value)
    );
  };

  return (
    <div className={classes.rotationTime}>
      <WatchIcon />
      <TextField
        autoSize
        dataTestId={labelRotationTime}
        inputProps={{ max: 60, min: 10 }}
        type="text"
        value={value}
        onChange={change}
      />
      <Typography>
        {pluralizedT({ count: value, label: labelSecond })}
      </Typography>
      <Typography>{t(labelRotationTime)}</Typography>
    </div>
  );
};

export default RotationTime;
