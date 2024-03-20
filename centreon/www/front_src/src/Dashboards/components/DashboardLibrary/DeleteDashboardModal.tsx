import { useRef } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { dashboardToDeleteAtom } from '../../atoms';
import { useDashboardDelete } from '../../hooks/useDashboardDelete';
import {
  labelCancel,
  labelDelete,
  labelDeleteDashboard,
  labelDescriptionDeleteDashboard
} from '../../translatedLabels';
import { Dashboard } from '../../api/models';

const DeleteDashboardModal = (): JSX.Element => {
  const dashboardRef = useRef('');

  const { t } = useTranslation();
  const [dashboardToDelete, setDashboardToDelete] = useAtom(
    dashboardToDeleteAtom
  );

  const deleteDashboard = useDashboardDelete();

  const confirm = (): void => {
    deleteDashboard(dashboardToDelete as Dashboard)();
    close();
  };

  const close = (): void => {
    setDashboardToDelete(null);
  };

  if (dashboardToDelete?.name) {
    dashboardRef.current = dashboardToDelete?.name;
  }

  return (
    <Modal open={Boolean(dashboardToDelete)} onClose={close}>
      <Modal.Header>{t(labelDeleteDashboard)}</Modal.Header>
      <Modal.Body>
        {t(labelDescriptionDeleteDashboard, { name: dashboardRef.current })}
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DeleteDashboardModal;
