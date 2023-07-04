import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import { Button } from '@mui/material';

import {
  EditedNotificationIdAtom,
  panelModeAtom
} from '../../../EditPanel/atom';
import { PanelMode } from '../../../EditPanel/models';
import { isPanelOpenAtom } from '../../../atom';
import { labelAdd } from '../../../translatedLabels';

const AddAction = (): JSX.Element => {
  const { t } = useTranslation();
  const setIsPannelOpen = useSetAtom(isPanelOpenAtom);
  const setPanelMode = useSetAtom(panelModeAtom);
  const setEditedNotificationId = useSetAtom(EditedNotificationIdAtom);
  const dataTestId = 'createNotification';

  const handleClick = (): void => {
    setEditedNotificationId(null);
    setPanelMode(PanelMode.Create);
    setIsPannelOpen(true);
  };

  return (
    <Button
      color="primary"
      data-testid={dataTestId}
      startIcon={<AddIcon />}
      variant="contained"
      onClick={handleClick}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddAction;
