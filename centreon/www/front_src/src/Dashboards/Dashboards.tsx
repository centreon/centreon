import { ReactElement, Suspense, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';

import { Modal, PageHeader, PageLayout } from '@centreon/ui/components';

import {
  labelCancel,
  labelDashboards,
  labelDelete,
  labelDeleteDashboard,
  labelDescriptionDeleteDashboardPartOne,
  labelDescriptionDeleteDashboardPartTwo,
} from './translatedLabels';
import { ListingSkeleton } from './ListingSkeleton';
import { deleteDialogStateAtom } from './atoms';
import useSubmitDashboard from './useSubmitDashboard';
import Listing from './Listing';
import useRemoveDashboard from './useRemoveDashboard';
import { DashboardFormModal } from './components/DashboardFormModal/DashboardFormModal';
import { DashboardAccessRightsModal } from './components/DashboardAccessRightsModal/DashboardAccessRightsModal';

import { ModalActionsLabels } from 'packages/ui/src/components/Modal/ModalActions';

const Dashboards = (): ReactElement => {
  const { t } = useTranslation();

  const [deleteDialogState, setDeleteDialogState] = useAtom(
    deleteDialogStateAtom
  );
  const { submit } = useSubmitDashboard();
  const { remove: removeDashboard } = useRemoveDashboard();

  const labels = useMemo(
    (): {
      deleteConfirmation: { actions: ModalActionsLabels };
      modalTitle: { delete: string };
    } => ({
      deleteConfirmation: {
        actions: {
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }
      },
      modalTitle: {
        delete: t(labelDeleteDashboard)
      }
    }),
    []
  );

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelDashboards)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Suspense fallback={<ListingSkeleton />}>
          <Listing />
        </Suspense>
      </PageLayout.Body>
      <DashboardFormModal />
      <DashboardAccessRightsModal />
      <Modal
        open={deleteDialogState.open}
        onClose={() =>
          setDeleteDialogState({
            ...deleteDialogState,
            open: false
          })
        }
      >
        <Modal.Header>{labels.modalTitle.delete}</Modal.Header>
        <Modal.Body>
          <p>
            {t(labelDescriptionDeleteDashboardPartOne)}
            <strong>{deleteDialogState.item?.name}</strong>
            {t(labelDescriptionDeleteDashboardPartTwo)}
          </p>
        </Modal.Body>
        <Modal.Actions
          isDanger
          labels={labels.deleteConfirmation.actions}
          onCancel={() =>
            setDeleteDialogState({ ...deleteDialogState, open: false })
          }
          onConfirm={() =>
            deleteDialogState.item && removeDashboard(deleteDialogState.item)
          }
        />
      </Modal>
    </PageLayout>
  );
};

export default Dashboards;
