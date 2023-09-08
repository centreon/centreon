import { ChangeEvent, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Box, FormControlLabel, Switch, Typography } from '@mui/material';

import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';
import { useSwitchStyles } from './Inputs.styles';

const WidgetSwitch = ({
  propertyName,
  label,
  endAdornment
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSwitchStyles();

  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<boolean | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const changeSwitchValue = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(`options.${propertyName}`, event.target.checked);
  };

  return (
    <FormControlLabel
      control={
        <Switch
          checked={value}
          inputProps={{
            'aria-label': t(label) || ''
          }}
          onChange={changeSwitchValue}
        />
      }
      label={
        <Box className={classes.switch}>
          <Typography>{t(label)}</Typography>
          {endAdornment}
        </Box>
      }
    />
  );
};

export default WidgetSwitch;
