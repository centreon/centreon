import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';

import { labelAdd } from '../../translatedLabels';
import { modalStateAtom } from '../../atom';
import { ModalMode } from '../../models';

const AddButton = (): JSX.Element => {
  const { t } = useTranslation();
  const setModalState = useSetAtom(modalStateAtom);
  const dataTestId = 'createResourceAccessRule';

  const click = (): void => {
    setModalState({
      isOpen: true,
      mode: ModalMode.Create
    });
  };

  return (
    <Button
      color="primary"
      data-testid={dataTestId}
      startIcon={<AddIcon />}
      variant="contained"
      onClick={click}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddButton;
