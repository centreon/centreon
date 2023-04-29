import { useSetAtom } from 'jotai';

import { Button } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';

import { isPanelOpenAtom } from '../../atom';

const ClosePanelAction = (): JSX.Element => {
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const handleClose = (): void => setIsPanelOpen(false);

  return (
    <Button onClick={handleClose}>
      <CloseIcon />
    </Button>
  );
};

export default ClosePanelAction;
