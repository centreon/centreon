import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import { RichTextEditor } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../models';
import { useCanEditProperties } from '../../../hooks/useCanEditDashboard';

import { getProperty } from './utils';

const WidgetRichTextEditor = ({
  propertyName,
  label,
  disabledCondition
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { errors, values, setFieldValue, setFieldTouched } =
    useFormikContext<Widget>();

  const { canEditField } = useCanEditProperties();

  const isGenericTextWidget = equals(
    values.moduleName,
    'centreon-widget-generictext'
  );

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName }),
    [getProperty({ obj: errors, propertyName })]
  );

  const change = (newEditiorState: unknown): void => {
    setFieldTouched(`options.${propertyName}`, true, false);
    setFieldValue(`options.${propertyName}`, JSON.stringify(newEditiorState));
  };

  return (
    <RichTextEditor
      editable
      openLinkInNewTab
      disabled={!canEditField || disabledCondition?.(values)}
      displayBlockButtons={isGenericTextWidget}
      editorState={value || undefined}
      error={error}
      getEditorState={change}
      initialEditorState={value || undefined}
      placeholder={t(label) as string}
      resetEditorToInitialStateCondition={() => isNil(value)}
    />
  );
};

export default WidgetRichTextEditor;
