import { useTranslation } from 'react-i18next';

import {
  Box,
  FormControlLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';

import { labelRefreshInterval } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import useRefreshInterval from './useRefreshInterval';

const RefreshInterval = ({
  propertyName
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { value, options, changeRefreshIntervalOption } = useRefreshInterval({
    propertyName
  });

  return (
    <Box>
      <Typography>
        <strong>{t(labelRefreshInterval)}</strong>
      </Typography>
      <RadioGroup
        aria-labelledby="demo-controlled-radio-buttons-group"
        name="controlled-radio-buttons-group"
        value={value}
        onChange={changeRefreshIntervalOption}
      >
        {options.map(({ value: optionValue, label }) => (
          <FormControlLabel
            control={<Radio />}
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
