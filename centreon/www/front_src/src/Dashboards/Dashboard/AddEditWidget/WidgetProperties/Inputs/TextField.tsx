import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { TextField } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';

const WidgetTextField = ({
  propertyName,
  label,
  text,
  required = false
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
    setFieldValue(`options.${propertyName}`, event.target.value);
  };

  return (
    <TextField
      fullWidth
      ariaLabel={t(label) as string}
      dataTestId={label}
      error={isTouched && error}
      helperText={isTouched && error}
      label={t(label)}
      multiline={text?.multiline || false}
      required={required}
      value={value || ''}
      onChange={change}
    />
  );
};

export default WidgetTextField;
