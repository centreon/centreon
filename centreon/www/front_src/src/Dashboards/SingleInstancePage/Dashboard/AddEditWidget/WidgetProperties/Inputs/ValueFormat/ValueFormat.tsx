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
import {
  labelHumanReadable,
  labelRawValue,
  labelValueFormat
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useValueFormat from './useValueFormat';

const WidgetValueFormat = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { value, changeType } = useValueFormat(propertyName);

  const { canEditField } = useCanEditProperties();

  const options = [
    {
      label: t(labelHumanReadable),
      optionValue: 'human'
    },
    {
      label: t(labelRawValue),
      optionValue: 'raw'
    }
  ];

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <Box>
      <Label>{t(labelValueFormat)}</Label>
      <RadioGroup value={value} onChange={changeType}>
        {options.map(({ optionValue, label }) => (
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

export default WidgetValueFormat;
