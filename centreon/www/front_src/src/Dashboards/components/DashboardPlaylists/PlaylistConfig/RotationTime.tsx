import { ChangeEvent } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import WatchIcon from '@mui/icons-material/Watch';
import { FormHelperText, Typography } from '@mui/material';

import {
  InputPropsWithoutGroup,
  TextField,
  usePluralizedTranslation
} from '@centreon/ui';

import { labelRotationTime, labelSecond } from '../../../translatedLabels';

import { usePlaylistConfigStyles } from './PlaylistConfig.styles';

const RotationTime = ({
  fieldName,
  getDisabled
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = usePlaylistConfigStyles();

  const { pluralizedT } = usePluralizedTranslation();
  const { values, setFieldValue, errors, touched, setFieldTouched } =
    useFormikContext<FormikValues>();

  const value = values[fieldName];
  const error = errors[fieldName];
  const isTouched = touched[fieldName];

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      fieldName,
      equals(event.target.value, '') || Number(event.target.value) < 0
        ? 10
        : Number(event.target.value)
    );
    setFieldTouched(fieldName, true, false);
  };

  return (
    <div>
      <div className={classes.rotationTime}>
        <WatchIcon className={classes.rotationTimeIcon} />
        <TextField
          autoSize
          dataTestId={labelRotationTime}
          disabled={getDisabled?.(values)}
          inputProps={{ max: 60, min: 10 }}
          size="compact"
          type="number"
          value={value}
          onChange={change}
        />
        <Typography>
          {pluralizedT({ count: value, label: labelSecond })}
        </Typography>
        <Typography>({t(labelRotationTime).toLocaleLowerCase()})</Typography>
      </div>
      {isTouched && error && (
        <FormHelperText error>{error as string | undefined}</FormHelperText>
      )}
    </div>
  );
};

export default RotationTime;
