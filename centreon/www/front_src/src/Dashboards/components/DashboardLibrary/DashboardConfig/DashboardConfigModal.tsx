import { ReactElement, useMemo } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  DashboardForm,
  DashboardFormLabels,
  DashboardResource,
  Modal
} from '@centreon/ui/components';

import {
  labelGlobalRefreshInterval,
  labelManualRefreshOnly
} from '../../../SingleInstancePage/Dashboard/translatedLabels';
import {
  labelCancel,
  labelCreate,
  labelCreateDashboard,
  labelDashboardCreated,
  labelDashboardUpdated,
  labelDescription,
  labelFailedToCreateDashboard,
  labelFailedToUpdateDashboard,
  labelName,
  labelUpdate,
  labelUpdateDashboard
} from '../../../translatedLabels';

import { useDashboardConfig } from './useDashboardConfig';

interface Props {
  showRefreshIntervalFields?: boolean;
}

const DashboardConfigModal = ({
  showRefreshIntervalFields
}: Props): ReactElement => {
  const { isDialogOpen, closeDialog, dashboard, submit, variant } =
    useDashboardConfig();

  const { t } = useTranslation();

  const labels = useMemo(
    (): {
      form: DashboardFormLabels;
      modalTitle: { create: string; update: string };
      status: {
        create: { error: string; success: string };
        update: { error: string; success: string };
      };
    } => ({
      form: {
        actions: {
          cancel: t(labelCancel),
          submit: {
            create: t(labelCreate),
            update: t(labelUpdate)
          }
        },
        entity: {
          description: t(labelDescription),
          globalRefreshInterval: {
            global: t(labelGlobalRefreshInterval),
            manual: t(labelManualRefreshOnly)
          },
          name: t(labelName)
        }
      },
      modalTitle: {
        create: t(labelCreateDashboard),
        update: t(labelUpdateDashboard)
      },
      status: {
        create: {
          error: t(labelFailedToCreateDashboard),
          success: t(labelDashboardCreated)
        },
        update: {
          error: t(labelFailedToUpdateDashboard),
          success: t(labelDashboardUpdated)
        }
      }
    }),
    []
  );

  return (
    <Modal open={isDialogOpen} onClose={closeDialog}>
      <Modal.Header>{labels.modalTitle[variant]}</Modal.Header>
      <Modal.Body>
        <DashboardForm
          labels={labels.form}
          resource={(dashboard as DashboardResource) ?? undefined}
          showRefreshIntervalFields={
            showRefreshIntervalFields && equals(variant, 'update')
          }
          variant={variant}
          onCancel={closeDialog}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export { DashboardConfigModal };
