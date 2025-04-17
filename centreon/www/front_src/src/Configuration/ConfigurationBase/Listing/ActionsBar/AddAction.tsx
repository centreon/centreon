import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router';
import { modalStateAtom } from '../../atoms';
import { labelAdd } from '../../translatedLabels';

const Add = (): JSX.Element => {
  const { t } = useTranslation();

  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(modalStateAtom);

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add' });

    setModalState({ id: null, isOpen: true, mode: 'add' });
  };

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid="add-resource"
      icon={<AddIcon />}
      iconVariant="start"
      size="small"
      variant="primary"
      onClick={openCreatetModal}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default Add;
