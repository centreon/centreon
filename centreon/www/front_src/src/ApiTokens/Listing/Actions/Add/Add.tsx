import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router';
import { ModalStateAtom } from '../../../atoms';
import { labelAdd, labelCreateNewToken } from '../../../translatedLabels';

const Add = (): JSX.Element => {
  const { t } = useTranslation();

  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(ModalStateAtom);

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add' });

    setModalState({ isOpen: true, mode: 'add' });
  };

  return (
    <Button
      aria-label={t(labelCreateNewToken)}
      data-testid={labelCreateNewToken}
      icon={<AddIcon />}
      iconVariant="start"
      size="medium"
      variant="primary"
      onClick={openCreatetModal}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default Add;
