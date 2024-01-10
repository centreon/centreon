import { useTranslation } from 'react-i18next';

import { Box, FormControlLabel, Radio, RadioGroup } from '@mui/material';

import { labelRefreshInterval } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useRefreshInterval from './useRefreshInterval';

const RefreshInterval = ({
  propertyName
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { value, options, changeRefreshIntervalOption } = useRefreshInterval({
    propertyName
  });

  const { canEditField } = useCanEditProperties();

  return (
    <Box>
      <Subtitle>{t(labelRefreshInterval)}</Subtitle>
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
