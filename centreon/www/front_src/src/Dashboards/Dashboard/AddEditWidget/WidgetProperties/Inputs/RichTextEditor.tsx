import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { RichTextEditor } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../models';

import { getProperty } from './utils';

const WidgetRichTextEditor = ({
  propertyName,
  label,
  disabledCondition
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { errors, values, setFieldValue, setFieldTouched } =
    useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName }),
    [getProperty({ obj: errors, propertyName })]
  );

  const openLinksInNewTab = useMemo<boolean | undefined>(
    () => getProperty({ obj: values, propertyName: 'openLinksInNewTab' }),
    [getProperty({ obj: values, propertyName: 'openLinksInNewTab' })]
  );

  const change = (newEditiorState: unknown): void => {
    setFieldTouched(`options.${propertyName}`, true, false);
    setFieldValue(`options.${propertyName}`, JSON.stringify(newEditiorState));
  };

  return (
    <RichTextEditor
      editable
      disabled={disabledCondition?.(values)}
      editorState={value || undefined}
      error={error}
      getEditorState={change}
      initialEditorState={value || undefined}
      openLinkInNewTab={openLinksInNewTab}
      placeholder={t(label) as string}
    />
  );
};

export default WidgetRichTextEditor;
