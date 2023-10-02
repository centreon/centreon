import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  DashboardForm,
  DashboardFormLabels,
  DashboardResource,
  Modal
} from '@centreon/ui/components';

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
} from '../../translatedLabels';

import { useDashboardConfig } from './useDashboardConfig';

const DashboardConfigModal = (): ReactElement => {
  const { isDialogOpen, closeDialog, dashboard, submit, variant, status } =
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
          variant={variant}
          onCancel={closeDialog}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export { DashboardConfigModal };
