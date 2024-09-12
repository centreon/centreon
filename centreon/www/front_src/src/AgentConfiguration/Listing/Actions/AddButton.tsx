import { Button } from '@centreon/ui/components';
import { Add } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { labelAddNewAgent } from '../../translatedLabels';

const AddButton = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button size="small" icon={<Add />} iconVariant="start">
      {t(labelAddNewAgent)}
    </Button>
  );
};

export default AddButton;
