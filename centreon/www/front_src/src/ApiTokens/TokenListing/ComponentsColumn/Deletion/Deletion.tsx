import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../../translatedLabels';

interface Parameters {
  close: () => void;
}

interface Props {
  disabled?: boolean;
  label?: string;
  renderModalConfirmation: (parameters: Parameters) => JSX.Element;
}

const Deletion = ({
  renderModalConfirmation,
  disabled = false,
  label = labelDelete
}: Props): JSX.Element => {
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
        ariaLabel={t(label) as string}
        data-testid={label}
        disabled={disabled}
        size="large"
        sx={{ marginLeft: 1 }}
        title={t(label)}
        onClick={displayModal}
      >
        <DeleteIcon />
      </IconButton>

      {displayConfirmationModal && renderModalConfirmation?.({ close })}
    </>
  );
};

export default Deletion;
