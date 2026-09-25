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

// The mock gives the panel 720px, holding a 680px content column.
export const defaultPanelWidth = 720;
const minPanelWidth = 550;

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
      // The mock bands the header in the page grey, above the white form.
      headerBackgroundColor={theme.palette.background.default}
      labelClose={t(labelClose)}
      // On a page too narrow to hold it, the panel is already below its own
      // minimum: dragging must not push it back out of the page.
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
