import { useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import CloseIcon from '@mui/icons-material/Close';

import { IconButton } from '@centreon/ui';

import { isPanelOpenAtom } from '../../../atom';
import { EditedNotificationIdAtom } from '../../atom';
import { labelClosePanel } from '../../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  button: {
    paddingLeft: theme.spacing(1.5)
  },
  icon: {
    color: theme.palette.text.primary,
    fontSize: theme.spacing(2.5)
  }
}));

const ClosePanelAction = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);
  const setEditedNotificationId = useSetAtom(EditedNotificationIdAtom);

  const handleClose = (): void => {
    setIsPanelOpen(false);
    setEditedNotificationId(null);
  };

  return (
    <IconButton
      ariaLabel={t(labelClosePanel) as string}
      className={classes.button}
      title={t(labelClosePanel) as string}
      onClick={handleClose}
    >
      <CloseIcon className={classes.icon} />
    </IconButton>
  );
};

export default ClosePanelAction;
