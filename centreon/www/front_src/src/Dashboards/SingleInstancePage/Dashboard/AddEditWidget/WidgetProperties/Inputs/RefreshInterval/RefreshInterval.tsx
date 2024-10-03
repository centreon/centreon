import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  Box,
  FormControlLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { labelRefreshInterval } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import useRefreshInterval from './useRefreshInterval';

const RefreshInterval = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { value, options, changeRefreshIntervalOption } = useRefreshInterval({
    propertyName
  });

  const { canEditField } = useCanEditProperties();

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <Box>
      <Label>{t(labelRefreshInterval)}</Label>
      <RadioGroup value={value} onChange={changeRefreshIntervalOption}>
        {options.map(({ value: optionValue, label }) => (
          <FormControlLabel
            control={<Radio data-testid={optionValue} />}
            disabled={!canEditField}
            key={optionValue}
            label={label}
            value={optionValue}
          />
        ))}
      </RadioGroup>
    </Box>
  );
};

export default RefreshInterval;
