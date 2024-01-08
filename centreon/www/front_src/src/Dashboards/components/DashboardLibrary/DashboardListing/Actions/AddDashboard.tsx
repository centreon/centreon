import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { labelAdd } from '../translatedLabels';

const AddDashboard = ({
  openConfig
}: {
  openConfig: () => void;
}): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid="create-dashboard"
      icon={<AddIcon />}
      iconVariant="start"
      size="small"
      variant="primary"
      onClick={openConfig}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddDashboard;
