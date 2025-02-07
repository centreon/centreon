import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { labelAdd } from '../../translatedLabels';

interface Props {
  openCreateDialog: () => void;
}

const Add = ({ openCreateDialog }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid="add-host-group"
      icon={<AddIcon />}
      iconVariant="start"
      size="medium"
      variant="primary"
      onClick={openCreateDialog}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default Add;
