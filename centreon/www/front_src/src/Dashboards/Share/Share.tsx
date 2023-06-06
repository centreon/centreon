import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';
import { useFetchQuery } from '@centreon/ui';

import { isShareModalOpenAtom } from '../atoms';

import { labelDashboardAccessRights } from './translatedLabels';

export const Share = (): JSX.Element => {
  const { t } = useTranslation();

  const [isShareModalOpen, setIsShareModalOpen] = useAtom(isShareModalOpenAtom);

  const closeModal = (): void => setIsShareModalOpen(false);

  return (
    <Modal open={isShareModalOpen} onClose={closeModal}>
      <Modal.Header>{t(labelDashboardAccessRights)}</Modal.Header>
    </Modal>
  );
};
