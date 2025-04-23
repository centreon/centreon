import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DashboardDuplicationForm, Modal } from '@centreon/ui/components';

import { dashboardToDuplicateAtom } from '../../atoms';
import { useDashboardDuplicate } from '../../hooks/useDashboardDuplicate';
import {
  labelCancel,
  labelDuplicate,
  labelDuplicateDashboard,
  labelName
} from '../../translatedLabels';

const DuplicateDashboardModal = (): JSX.Element => {
  const { t } = useTranslation();
  const [dashboardToDuplicate, setDashboardToDuplicate] = useAtom(
    dashboardToDuplicateAtom
  );

  const duplicateDashboard = useDashboardDuplicate();

  const submit = ({ name }): void => {
    duplicateDashboard(name);

    close();
  };

  const close = (): void => {
    setDashboardToDuplicate(null);
  };

  const labels = {
    actions: {
      cancel: t(labelCancel),
      submit: {
        create: t(labelDuplicate)
      }
    },
    entity: {
      name: t(labelName)
    }
  };

  return (
    <Modal open={Boolean(dashboardToDuplicate)} onClose={close}>
      <Modal.Header>{t(labelDuplicateDashboard)}</Modal.Header>
      <Modal.Body>
        <DashboardDuplicationForm
          labels={labels}
          name={`${dashboardToDuplicate?.name}_1`}
          onCancel={close}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export default DuplicateDashboardModal;
