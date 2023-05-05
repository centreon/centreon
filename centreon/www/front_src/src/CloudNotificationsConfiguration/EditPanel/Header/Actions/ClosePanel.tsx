import { useSetAtom } from 'jotai';

import { Button } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';

import { isPanelOpenAtom } from '../../../atom';
import { EditedNotificationIdAtom } from '../../atom';

const ClosePanelAction = (): JSX.Element => {
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);
  const setEditedNotificationId = useSetAtom(EditedNotificationIdAtom);

  const handleClose = (): void => {
    setIsPanelOpen(false);
    setEditedNotificationId(null);
  };

  return (
    <Button onClick={handleClose}>
      <CloseIcon />
    </Button>
  );
};

export default ClosePanelAction;
