import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';
import { modalStateAtom } from '../../atoms';
import { TokenType } from '../../models';
import { labelAdd } from '../../translatedLabels';

const Add = (): JSX.Element => {
  const { t } = useTranslation();

  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(modalStateAtom);

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add', type: TokenType.API });

    setModalState({ isOpen: true, mode: 'add', type: TokenType.API });
  };

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid={labelAdd}
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
