import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';
import { useSetAtom } from 'jotai';

import AddIcon from '@mui/icons-material/Add';
import { Theme, Typography, Box, Button } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import {
  labelWelcomeToTheNotifications,
  labelCreateNotification
} from '../translatedLabels';
import Title from '../Title';
import { isPanelOpenAtom } from '../atom';

import EmptyNotificationsIcon from './EmptyNotificationsIcon';

export const isDarkMode = (theme: Theme): boolean =>
  equals(theme.palette.mode, ThemeMode.dark);

const useStyle = makeStyles()((theme: Theme) => ({
  btn: {
    textTransform: 'uppercase'
  },
  container: {
    padding: `0 ${theme.spacing(3)}`
  },
  content: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(3),
    marginTop: theme.spacing(8)
  },
  icon: {
    marginBottom: theme.spacing(3)
  },
  text: {
    fontWeight: theme.typography.fontWeightBold
  },
  title: {
    maxWidth: '90%'
  }
}));

const EmptyNotificationsPage = (): JSX.Element => {
  const { classes } = useStyle();
  const { t } = useTranslation();
  const setIsOpen = useSetAtom(isPanelOpenAtom);

  return (
    <Box className={classes.container}>
      <Box className={classes.title}>
        <Title />
      </Box>
      <Box className={classes.content}>
        <Box className={classes.icon}>
          <EmptyNotificationsIcon />
        </Box>

        <Typography className={classes.text} variant="h4">
          {t(labelWelcomeToTheNotifications)}
        </Typography>
        <Button
          className={classes.btn}
          color="primary"
          startIcon={<AddIcon />}
          variant="contained"
          onClick={(): void => setIsOpen(true)}
        >
          {t(labelCreateNotification)}
        </Button>
      </Box>
    </Box>
  );
};

export default EmptyNotificationsPage;
