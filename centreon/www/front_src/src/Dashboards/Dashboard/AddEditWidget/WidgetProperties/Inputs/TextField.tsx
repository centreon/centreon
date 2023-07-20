import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { TextField } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';

const WidgetTextField = ({
  propertyName,
  label
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(`options.${propertyName}`, event.target.value);
  };

  return (
    <TextField
      fullWidth
      ariaLabel={t(label) as string}
      dataTestId={label}
      label={t(label)}
      value={value}
      onChange={change}
    />
  );
};

export default WidgetTextField;
