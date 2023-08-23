import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { SelectField } from '@centreon/ui';

import { labelTimePeriod } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import useTimePeriod from './useTimePeriod';

const TimePeriod = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { value, setTimePeriod, options } = useTimePeriod(propertyName);

  return (
    <div>
      <Typography>
        <strong>{t(labelTimePeriod)}</strong>
      </Typography>
      <SelectField
        options={options}
        selectedOptionId={value.timePeriodType || ''}
        onChange={setTimePeriod}
      />
    </div>
  );
};

export default TimePeriod;
