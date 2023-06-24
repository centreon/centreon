import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { labelAdd } from '../../translatedLabels';
import { isPanelOpenAtom } from '../../atom';
import { editedNotificationIdAtom, panelModeAtom } from '../../EditPanel/atom';
import { PanelMode } from '../../EditPanel/models';

const AddAction = (): JSX.Element => {
  const { t } = useTranslation();
  const setIsPannelOpen = useSetAtom(isPanelOpenAtom);
  const setPanelMode = useSetAtom(panelModeAtom);
  const setEditedNotificationId = useSetAtom(editedNotificationIdAtom);

  const onClick = (): void => {
    setEditedNotificationId(null);
    setPanelMode(PanelMode.Create);
    setIsPannelOpen(true);
  };

  return (
    <Button
      color="primary"
      startIcon={<AddIcon />}
      variant="contained"
      onClick={onClick}
    >
      {t(labelAdd)}
    </Button>
  );
};

export default AddAction;
