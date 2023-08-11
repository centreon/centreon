import { useTranslation } from 'react-i18next';

import {
  Box,
  FormControlLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';

import { Tooltip } from '@centreon/ui/components';

import { WidgetPropertyProps } from '../../../models';
import {
  labelShowThresholds,
  labelThreshold,
  labelThresholdsAreAutomaticallyHidden
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
        endAdornment={
          <Tooltip
            followCursor={false}
            label={t(labelThresholdsAreAutomaticallyHidden)}
            position="top"
          >
            <InfoOutlinedIcon color="primary" />
          </Tooltip>
        }
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
