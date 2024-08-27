import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { clamp, equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { TextField } from '@centreon/ui';

import { useCanEditProperties } from '../../../hooks/useCanEditDashboard';
import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';

const WidgetTextField = ({
  propertyName,
  label,
  text,
  required = false,
  disabled = false,
  className
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { errors, values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const { canEditField } = useCanEditProperties();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName }),
    [getProperty({ obj: errors, propertyName })]
  );

  const isTouched = useMemo<string | undefined>(
    () => getProperty({ obj: touched, propertyName }),
    [getProperty({ obj: touched, propertyName })]
  );

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched(`options.${propertyName}`, true);

    if (equals(text?.type, 'number')) {
      setFieldValue(
        `options.${propertyName}`,
        equals(event.target.value, '')
          ? ''
          : clamp(text?.min, text?.max, Number(event.target.value))
      );

      return;
    }
    const newText = event.target.value;
    setFieldValue(`options.${propertyName}`, newText);
  };

  return (
    <TextField
      fullWidth
      autoSize={text?.autoSize}
      autoSizeDefaultWidth={8}
      className={className}
      dataTestId={label}
      disabled={!canEditField || disabled}
      error={isTouched && error}
      helperText={isTouched && error}
      inputProps={{
        'aria-label': t(label) as string,
        max: text?.max,
        min: text?.min,
        step: text?.step || '1'
      }}
      label={t(label) || ''}
      multiline={text?.multiline || false}
      required={required}
      size={text?.size || 'small'}
      type={text?.type || 'text'}
      value={value ?? ''}
      onChange={change}
    />
  );
};

export default WidgetTextField;
