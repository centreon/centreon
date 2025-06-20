import { FormHelperText, Stack } from '@mui/material';
import { useFormikContext } from 'formik';
import { equals, isNil } from 'ramda';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  labelMaxValue,
  labelMinMustLowerThanMax,
  labelMinValue
} from '../../../translatedLabels';
import { WidgetPropertyProps } from '../../models';
import WidgetTextField from './TextField';
import { options } from './TimePeriod/useTimePeriod';
import { getProperty } from './utils';

const Boundaries = ({ propertyName, text }: WidgetPropertyProps) => {
  const { t } = useTranslation();
  const { errors, touched, setFieldValue, values, setErrors } =
    useFormikContext();

  const boundaryMin = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName: `${propertyName}.min` }),
    [getProperty({ obj: values, propertyName: `${propertyName}.min` })]
  );

  const boundaryMax = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: values, propertyName: `${propertyName}.max` })]
  );

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: errors, propertyName: `${propertyName}.max` })]
  );

  const isTouched = useMemo<string | undefined>(
    () => getProperty({ obj: touched, propertyName: `${propertyName}.max` }),
    [getProperty({ obj: touched, propertyName: `${propertyName}.max` })]
  );

    const boundariesType = getProperty({
    obj: values,
    propertyName: 'boundariesType'
  });
  const validateBoundaries = () => {

    if (equals(boundariesType, 'auto')) {
      return;
    }

    if (isNil(boundaryMax) || isNil(boundaryMin)) {
      return;
    }

    if (boundaryMin >= boundaryMax) {
      const boundaryError = {
        [propertyName]: { max: labelMinMustLowerThanMax }
      };

      setErrors(
        'options' in errors
          ? { ...errors, options: { ...options.errors, ...boundaryError } }
          : { ...errors, options: boundaryError }
      );
    }
  };  
  
  validateBoundaries();

  useEffect(() => {
    if (boundaryMin && boundaryMax) {
      return;
    }
    setFieldValue(`options.${propertyName}`, { min: 0, max: 100 });
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
