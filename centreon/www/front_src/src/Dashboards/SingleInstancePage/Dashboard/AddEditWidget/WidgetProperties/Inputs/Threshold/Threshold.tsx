import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import {
  Box,
  FormControlLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { WidgetSwitch } from '..';
import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import {
  labelShowThresholds,
  labelThresholds,
  labelThresholdsAreAutomaticallyHidden
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useThresholdStyles } from '../Inputs.styles';

import useThreshold from './useThreshold';

const Threshold = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useThresholdStyles();

  const { changeType, options, enabled } = useThreshold({
    propertyName
  });

  const { canEditField } = useCanEditProperties();

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <Box>
      <Label>{t(labelThresholds)}</Label>
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
      {enabled && (
        <Box className={classes.thresholds}>
          {options.map(({ label, radioButtons, type, value }) => (
            <div key={label}>
              <Typography>{label}</Typography>
              <RadioGroup row value={value} onChange={changeType(type)}>
                {radioButtons.map(({ content, value: radioValue }) => (
                  <FormControlLabel
                    control={<Radio data-testid={radioValue} />}
                    disabled={!canEditField}
                    key={radioValue}
                    label={content}
                    value={radioValue}
                  />
                ))}
              </RadioGroup>
            </div>
          ))}
        </Box>
      )}
    </Box>
  );
};

export default Threshold;
