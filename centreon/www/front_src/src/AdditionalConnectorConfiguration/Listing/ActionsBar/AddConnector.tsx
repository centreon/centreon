import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { labelAdd } from '../../translatedLabels';

const AddConnector = ({
  openConfig
}: {
  openConfig: () => void;
}): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button
      aria-label={t(labelAdd)}
      data-testid="create-connector-configuration"
      icon={<AddIcon />}
      iconVariant="start"
      size="medium"
      variant="primary"
      onClick={openConfig}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddConnector;
