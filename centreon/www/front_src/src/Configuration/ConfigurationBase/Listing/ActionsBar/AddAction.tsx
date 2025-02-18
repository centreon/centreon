import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { dialogStateAtom } from '../../Modal/atoms';
import { labelAdd } from '../../translatedLabels';

const Add = (): JSX.Element => {
  const { t } = useTranslation();

  const setDialogState = useSetAtom(dialogStateAtom);

  const openCreatetModal = (): void => {
    setDialogState({ id: null, isOpen: true, variant: 'create' });
  };

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid="add-resource"
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
