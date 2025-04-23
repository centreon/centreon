import { FormHelperText, Stack } from '@mui/material';
import { WidgetPropertyProps } from '../../models';
import WidgetTextField from './TextField';
import { labelMaxValue, labelMinValue } from '../../../translatedLabels';
import { useFormikContext } from 'formik';
import { useMemo } from 'react';
import { getProperty } from './utils';
import { useTranslation } from 'react-i18next';

const Boundaries = ({ propertyName, text }: WidgetPropertyProps) => {
  const { t } = useTranslation();
  const { errors, touched } = useFormikContext();

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: errors, propertyName: `${propertyName}.max` })]
  );

  const isTouched = useMemo<string | undefined>(
    () => getProperty({ obj: touched, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: touched, propertyName: `${propertyName}.max` })]
  );

  return (
    <div>
      <Stack direction="row" gap={1.5} alignItems="center">
        <WidgetTextField
          ignoreError
          propertyName={`${propertyName}.min`}
          label={labelMinValue}
          text={text}
          isInGroup={false}
          isSingleAutocomplete={false}
        />
        <div>-</div>
        <WidgetTextField
          ignoreError
          propertyName={`${propertyName}.max`}
          label={labelMaxValue}
          text={text}
          isInGroup={false}
          isSingleAutocomplete={false}
        />
      </Stack>
      {isTouched && error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default Boundaries;
