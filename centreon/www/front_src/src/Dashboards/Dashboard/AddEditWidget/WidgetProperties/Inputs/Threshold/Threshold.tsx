import { useTranslation } from 'react-i18next';

import {
  Box,
  FormControlLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';

import { WidgetPropertyProps } from '../../../models';
import {
  labelShowThresholds,
  labelThreshold
} from '../../../../translatedLabels';
import { WidgetSwitch } from '..';
import { useThresholdStyles } from '../Inputs.styles';

import useThreshold from './useThreshold';

const Threshold = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useThresholdStyles();

  const { changeType, options } = useThreshold({
    propertyName
  });

  return (
    <Box>
      <Typography>
        <strong>{t(labelThreshold)}</strong>
      </Typography>
      <WidgetSwitch
        label={t(labelShowThresholds)}
        propertyName={`${propertyName}.enabled`}
      />
      {options.map(({ label, radioButtons, type, value }) => (
        <Box className={classes.threshold} key={label}>
          <Typography>{label}</Typography>
          <RadioGroup row value={value} onChange={changeType(type)}>
            {radioButtons.map(({ content, value: radioValue }) => (
              <FormControlLabel
                control={<Radio />}
                key={radioValue}
                label={content}
                value={radioValue}
              />
            ))}
          </RadioGroup>
        </Box>
      ))}
    </Box>
  );
};

export default Threshold;
