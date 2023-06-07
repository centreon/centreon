import { Suspense } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { selectedDashboardShareAtom } from '../atoms';

import { labelDashboardAccessRights } from './translatedLabels';
import SharesList from './SharesList';
import Skeleton from './Skeleton';

export const Share = (): JSX.Element => {
  const { t } = useTranslation();

  const [selectedDashboardShare, setSelectedDashboardShare] = useAtom(
    selectedDashboardShareAtom
  );

  const closeModal = (): void => setSelectedDashboardShare(undefined);

  return (
    <Modal
      open={Boolean(selectedDashboardShare)}
      size="medium"
      onClose={closeModal}
    >
      <Modal.Header>{t(labelDashboardAccessRights)}</Modal.Header>
      <Suspense fallback={<Skeleton />}>
        <SharesList />
      </Suspense>
    </Modal>
  );
};
