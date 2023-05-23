import { Suspense, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import { TiledListingPage } from '@centreon/ui';
import {
  DashboardForm,
  DashboardFormLabels,
  DashboardResource,
  Header,
  Modal
} from '@centreon/ui/components';

import {
  labelCancel,
  labelCreate,
  labelCreateDashboard,
  labelDashboards,
  labelDelete,
  labelDeleteDashboard,
  labelDescription,
  labelDescriptionDeleteDashboard,
  labelName,
  labelUpdate,
  labelUpdateDashboard
} from './translatedLabels';
import ListingSkeleton from './Skeleton';
import {
  closeDialogAtom,
  deleteDialogStateAtom,
  isDialogOpenAtom,
  selectedDashboardAtom
} from './atoms';
import useSubmitDashboard from './useSubmitDashboard';
import Listing from './Listing';
import useRemoveDashboard from './useRemoveDashboard';

import { ModalActionsLabels } from 'packages/ui/src/components/Modal/ModalActions';

const Dashboards = (): JSX.Element => {
  const { t } = useTranslation();

  const [deleteDialogState, setDeleteDialogState] = useAtom(
    deleteDialogStateAtom
  );
  const isDialogOpen = useAtomValue(isDialogOpenAtom);
  const selectedDashboard = useAtomValue(selectedDashboardAtom);
  const closeDialog = useSetAtom(closeDialogAtom);

  const { submit } = useSubmitDashboard();
  const { remove: removeDashboard } = useRemoveDashboard();

  const labels = useMemo(
    (): {
      deleteConfirmation: { actions: ModalActionsLabels; description: string };
      form: DashboardFormLabels;
      modalTitle: { create: string; delete: string; update: string };
    } => ({
      deleteConfirmation: {
        actions: {
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        },
        description: t(labelDescriptionDeleteDashboard)
      },
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
        delete: t(labelDeleteDashboard),
        update: t(labelUpdateDashboard)
      }
    }),
    [t]
  );

  return (
    <TiledListingPage>
      <Header title={t(labelDashboards)} />
      <Suspense fallback={<ListingSkeleton />}>
        <Listing />
      </Suspense>
      <Modal open={isDialogOpen} onClose={closeDialog}>
        <Modal.Header>
          {labels.modalTitle[selectedDashboard?.variant ?? 'create']}
        </Modal.Header>
        <Modal.Body>
          <DashboardForm
            labels={labels.form}
            resource={
              (selectedDashboard?.dashboard as DashboardResource) || undefined
            }
            variant={selectedDashboard?.variant}
            onCancel={closeDialog}
            onSubmit={submit}
          />
        </Modal.Body>
      </Modal>
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
          <p
            /* eslint-disable-next-line react/no-danger */
            dangerouslySetInnerHTML={{
              __html:
                t(labelDescriptionDeleteDashboard, {
                  name: deleteDialogState.item?.name
                }) || ''
            }}
          />
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
    </TiledListingPage>
  );
};

export default Dashboards;
