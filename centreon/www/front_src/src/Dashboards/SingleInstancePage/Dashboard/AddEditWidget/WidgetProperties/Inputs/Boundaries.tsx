import { FormHelperText, Stack } from '@mui/material';
import { useFormikContext } from 'formik';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { labelMaxValue, labelMinValue } from '../../../translatedLabels';
import { WidgetPropertyProps } from '../../models';
import WidgetTextField from './TextField';
import { getProperty } from './utils';

const Boundaries = ({ propertyName, text }: WidgetPropertyProps) => {
  const { t } = useTranslation();
  const { errors, touched, setFieldValue } = useFormikContext();

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: errors, propertyName: `${propertyName}.max` })]
  );

  const isTouched = useMemo<string | undefined>(
    () => getProperty({ obj: touched, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: touched, propertyName: `${propertyName}.max` })]
  );

  useEffect(() => {
    return () => {
      setFieldValue(`options.${propertyName}`, undefined);
    };
  }, []);

  return (
    <div>
      <Stack direction="row" gap={1.5} alignItems="center">
        <WidgetTextField
          ignoreError
          propertyName={`${propertyName}.min`}
          label={labelMinValue}
          text={{ type: 'number', ...text }}
          isInGroup={false}
          isSingleAutocomplete={false}
        />
        <div>-</div>
        <WidgetTextField
          ignoreError
          propertyName={`${propertyName}.max`}
          label={labelMaxValue}
          text={{ type: 'number', ...text }}
          isInGroup={false}
          isSingleAutocomplete={false}
        />
      </Stack>
      {isTouched && error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default Boundaries;
