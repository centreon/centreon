import { useTheme } from '@mui/material';

import { Panel } from '@centreon/ui';

import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { Form as FormType } from '../../models';
import { Form, useForm } from '../Form';
import { labelClose } from '../translatedLabels';
import { panelDataTestIds } from './dataTestIds';
import Header from './Header';
import { usePanelStyles } from './Panel.styles';

// The design sizes the panel between these two, and opens it at its narrowest.
export const minPanelWidth = 720;
export const maxPanelWidth = 1368;
export const defaultPanelWidth = minPanelWidth;

interface Props {
  form: FormType;
  hasWriteAccess: boolean;
  onResize: (width: number) => void;
  width: number;
}

const FormPanel = ({
  form,
  hasWriteAccess,
  onResize,
  width
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();
  const { classes } = usePanelStyles();

  const { labelHeader, submit, close, mode, id, initialValues, isLoading } =
    useForm({ defaultValues: form.defaultValues, hasWriteAccess });

  const loadedResource = equals(mode, 'edit')
    ? (initialValues as Record<string, unknown>)
    : undefined;

  return (
    <Panel
      className={classes.panel}
      header={
        <Header fallbackTitle={labelHeader} loadedResource={loadedResource} />
      }
      headerBackgroundColor={theme.palette.background.default}
      labelClose={t(labelClose)}
      // Already below its own minimum on a narrow page: dragging must not
      // push it back out.
      minWidth={Math.min(minPanelWidth, width)}
      onClose={close}
      onResize={onResize}
      selectedTab={
        <div className="px-5 pb-5" data-testid={panelDataTestIds.content}>
          <Form
            areActionsInHeader
            groups={form?.groups}
            hasWriteAccess={hasWriteAccess}
            id={id}
            initialValues={initialValues}
            inputs={form?.inputs}
            isLoading={isLoading}
            mode={mode}
            onCancel={close}
            onSubmit={submit}
            validationSchema={form?.validationSchema}
          />
        </div>
      }
      width={width}
    />
  );
};

export default FormPanel;
