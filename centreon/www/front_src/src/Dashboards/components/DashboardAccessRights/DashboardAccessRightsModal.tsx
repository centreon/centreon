import { ReactElement, Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { labelEditAccessRights } from '../../translatedLabels';

import { useDashboardAccessRights } from './useDashboardAccessRights';
import { DashboardAccessRightsForm } from './DashboardAccessRightsForm';

const DashboardAccessRightsModal = (): ReactElement => {
  const { isDialogOpen, closeDialog } = useDashboardAccessRights();

  const { t } = useTranslation();

  return (
    <Modal open={isDialogOpen} size="medium" onClose={closeDialog}>
      <Modal.Header>{t(labelEditAccessRights)}</Modal.Header>
      <Modal.Body>
        <Suspense>
          <DashboardAccessRightsForm />
        </Suspense>
      </Modal.Body>
    </Modal>
  );
};

export { DashboardAccessRightsModal };
