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
import Subtitle from '../../../../components/Subtitle';

import useThreshold from './useThreshold';

const Threshold = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useThresholdStyles();

  const { changeType, options } = useThreshold({
    propertyName
  });

  return (
    <Box>
      <Subtitle>{t(labelThreshold)}</Subtitle>
      <div className={classes.showThreshold}>
        <WidgetSwitch
          endAdornment={
            <Tooltip
              followCursor={false}
              label={t(labelThresholdsAreAutomaticallyHidden)}
              position="right"
            >
              <InfoOutlinedIcon color="primary" />
            </Tooltip>
          }
          label={t(labelShowThresholds)}
          propertyName={`${propertyName}.enabled`}
        />
      </div>
      <Box className={classes.thresholds}>
        {options.map(({ label, radioButtons, type, value }) => (
          <div key={label}>
            <Typography>{label}</Typography>
            <RadioGroup row value={value} onChange={changeType(type)}>
              {radioButtons.map(({ content, value: radioValue }) => (
                <FormControlLabel
                  control={<Radio data-testid={radioValue} />}
                  key={radioValue}
                  label={content}
                  value={radioValue}
                />
              ))}
            </RadioGroup>
          </div>
        ))}
      </Box>
    </Box>
  );
};

export default Threshold;
