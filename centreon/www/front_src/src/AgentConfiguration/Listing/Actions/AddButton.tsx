import { Button } from '@centreon/ui/components';
import { Add } from '@mui/icons-material';
import { useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { openFormModalAtom } from '../../atoms';
import { labelAdd } from '../../translatedLabels';

const AddButton = (): JSX.Element => {
  const { t } = useTranslation();

  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const add = useCallback(() => setOpenFormModal('add'), []);

  return (
    <Button
      size="small"
      icon={<Add />}
      iconVariant="start"
      onClick={add}
      data-testid="add-agent-configuration"
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddButton;
