import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../../translatedLabels';

import ConfirmationDeletionModal from './ConfirmationDeletionModal';

const Deletion = (): JSX.Element => {
  const { t } = useTranslation();

  const [displayConfirmationModal, setDisplayConfirmationModal] =
    useState(false);

  const displayModal = (): void => {
    setDisplayConfirmationModal(true);
  };

  const close = (): void => {
    setDisplayConfirmationModal(false);
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

      {displayConfirmationModal && (
        <ConfirmationDeletionModal
          close={close}
          open={displayConfirmationModal}
        />
      )}
    </>
  );
};

export default Deletion;
