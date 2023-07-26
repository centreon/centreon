import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';

const WidgetRichTextEditor = ({
  propertyName,
  label,
  text,
  required = false
}: WidgetPropertyProps) => {
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
};

export default WidgetRichTextEditor;
