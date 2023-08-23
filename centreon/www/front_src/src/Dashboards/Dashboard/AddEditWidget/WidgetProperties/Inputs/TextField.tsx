import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { TextField } from '@centreon/ui';

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
    const newText = equals(text?.type, 'number')
      ? parseInt(event.target.value || '0', 10)
      : event.target.value;
    setFieldValue(`options.${propertyName}`, newText);
  };

  const isCompact = equals(text?.size, 'compact');

  return (
    <TextField
      fullWidth
      ariaLabel={t(label) as string}
      className={className}
      dataTestId={label}
      disabled={disabled}
      error={isTouched && error}
      helperText={isTouched && error}
      label={isCompact ? null : t(label) || ''}
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
