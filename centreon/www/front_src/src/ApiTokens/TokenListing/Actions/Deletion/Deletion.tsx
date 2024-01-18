import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../../translatedLabels';

import { displayConfirmationModalAtom } from './atoms';
import ConfirmationDeletionModal from './ConfirmationDeletionModal';

const Deletion = (): JSX.Element => {
  const { t } = useTranslation();

  const [displayConfirmationModal, setDisplayConfirmationModal] = useAtom(
    displayConfirmationModalAtom
  );

  const displayModal = (): void => {
    setDisplayConfirmationModal(true);
  };

  return (
    <>
      <IconButton
        ariaLabel={t(labelDelete) as string}
        data-testid={labelDelete}
        size="large"
        sx={{ marginLeft: 1 }}
        title={t(labelDelete)}
        onClick={displayModal}
      >
        <DeleteIcon />
      </IconButton>

      {displayConfirmationModal && <ConfirmationDeletionModal />}
    </>
  );
};

export default Deletion;
