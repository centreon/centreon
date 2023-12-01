import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { labelCreate } from '../translatedLabels';

const AddPlaylist = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button
      aria-label={t(labelCreate)}
      data-testid="add-widget"
      icon={<AddIcon />}
      iconVariant="start"
      size="small"
      variant="primary"
      onClick={() => undefined}
    >
      {t(labelCreate)}
    </Button>
  );
};

export default AddPlaylist;
