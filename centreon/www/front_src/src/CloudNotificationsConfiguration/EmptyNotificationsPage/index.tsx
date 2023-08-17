import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import AddIcon from '@mui/icons-material/Add';
import { Box, Button, Theme, Typography } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import Title from '../Title';
import { isPanelOpenAtom } from '../atom';
import {
  labelCreateNotification,
  labelWelcomeToTheNotifications
} from '../translatedLabels';

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
  const dataTestId = 'createNotificationForTheFirstTime';

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
          data-testid={dataTestId}
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
