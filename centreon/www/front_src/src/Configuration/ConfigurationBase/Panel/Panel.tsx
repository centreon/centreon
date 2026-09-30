import { useTheme } from '@mui/material';

import { Panel } from '@centreon/ui';

import { equals } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Form as FormType } from '../../models';
import { ResetDialog } from '../Dialogs';
import { Form, useForm } from '../Form';
import { labelClose } from '../translatedLabels';
import { panelDataTestIds } from './dataTestIds';
import Header from './Header';
import { usePanelStyles } from './Panel.styles';

// The three widths the design lays the form out at.
const panelWidths = { large: 1400, medium: 900, small: 720 };

export const minPanelWidth = panelWidths.small;
export const maxPanelWidth = panelWidths.large;

// The panel opens at a width the screen can hold, and the user takes it from
// there. Thresholds are the screens the design sizes for: 1920, then 14" and
// 1440 desktops, then 13".
export const getDefaultPanelWidth = (viewportWidth: number): number => {
  if (viewportWidth >= 1920) {
    return panelWidths.large;
  }

  if (viewportWidth >= 1440) {
    return panelWidths.medium;
  }

  return panelWidths.small;
};

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
}: Props): ReactElement => {
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
          <ResetDialog />
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
