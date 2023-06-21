import { ReactElement, useEffect, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Modal, ModalActionsLabels } from '@centreon/ui/components';
import { sanitizedHTML, useSnackbar } from '@centreon/ui';

import {
  labelCancel,
  labelDashboardDeleted,
  labelDelete,
  labelDeleteDashboard,
  labelDescriptionDeleteDashboard,
  labelFailedToDeleteDashboard
} from '../../translatedLabels';

import { useDashboardDelete } from './useDashboardDelete';

const DashboardDeleteConfirmationModal = (): ReactElement => {
  const { isDialogOpen, closeDialog, dashboard, submit, status } =
    useDashboardDelete();

  const { t } = useTranslation();

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const onSuccess = (): void => showSuccessMessage(labels.status.success);

  const onError = (): void => showErrorMessage(labels.status.error);

  useEffect(() => {
    if (status === 'success') onSuccess();
    if (status === 'error') onError();
  }, [status]);

  const labels = useMemo(
    (): {
      modal: {
        actions: ModalActionsLabels;
        description: (name) => string;
        title: string;
      };
      status: { error: string; success: string };
    } => ({
      modal: {
        actions: {
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        },
        description: (name: string) =>
          t(labelDescriptionDeleteDashboard, { name }),
        title: t(labelDeleteDashboard)
      },
      status: {
        error: t(labelFailedToDeleteDashboard),
        success: t(labelDashboardDeleted)
      }
    }),
    []
  );

  return (
    <Modal open={isDialogOpen} onClose={closeDialog}>
      <Modal.Header>{labels.modal.title}</Modal.Header>
      <Modal.Body>
        <p>
          {sanitizedHTML({
            initialContent: labels.modal.description(dashboard?.name)
          })}
        </p>
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={labels.modal.actions}
        onCancel={closeDialog}
        onConfirm={() => dashboard && submit(dashboard)}
      />
    </Modal>
  );
};

export { DashboardDeleteConfirmationModal };
