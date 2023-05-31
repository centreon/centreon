import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { labelAdd } from '../translatedLabels';
import { isPanelOpenAtom } from '../atom';

const AddAction = (): JSX.Element => {
  const { t } = useTranslation();
  const setIsPannelOpen = useSetAtom(isPanelOpenAtom);

  const handleClick = (): void => {
    setIsPannelOpen(true);
  };

  return (
    <Button
      color="primary"
      startIcon={<AddIcon />}
      variant="contained"
      onClick={handleClick}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddAction;
