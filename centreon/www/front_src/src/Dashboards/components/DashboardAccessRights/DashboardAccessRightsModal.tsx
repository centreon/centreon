import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import {
  labelCancel,
  labelEditAccessRights,
  labelUpdate
} from '../../translatedLabels';

import { useDashboardAccessRights } from './useDashboardAccessRights';

const DashboardAccessRightsModal = (): ReactElement => {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const { isDialogOpen, closeDialog, dashboard, submit, status } =
    useDashboardAccessRights();

  const { t } = useTranslation();

  const labels = useMemo(
    (): {
      form: {
        actions: {
          cancel: string;
          confirm: string;
        };
      };
      modalTitle: string;
    } => ({
      form: {
        actions: {
          cancel: t(labelCancel),
          confirm: t(labelUpdate)
        }
      },
      modalTitle: t(labelEditAccessRights)
    }),
    []
  );

  return (
    <Modal open={isDialogOpen} onClose={closeDialog}>
      <Modal.Header>{labels.modalTitle}</Modal.Header>
      <Modal.Body />
      <Modal.Actions
        labels={labels.form.actions}
        onCancel={closeDialog}
        // onConfirm={submit}
      />
    </Modal>
  );
};

export { DashboardAccessRightsModal };
