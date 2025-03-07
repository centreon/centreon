import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useNavigate } from 'react-router';
import { labelAdd } from '../../translatedLabels';

const Add = (): JSX.Element => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const openCreatetModal = (): void => {
    navigate('/main.php?p=60102&o=a');
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
