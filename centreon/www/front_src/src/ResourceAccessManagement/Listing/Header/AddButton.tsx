import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';

import { labelAdd } from '../../translatedLabels';
import { modalStateAtom } from '../../atom';

const AddButton = (): JSX.Element => {
  const { t } = useTranslation();
  const [modalState, setModalState] = useAtom(modalStateAtom);
  const dataTestId = 'createResourceAccessRule';

  const click = (): void => {
    setModalState({
      ...modalState,
      isOpen: true
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
