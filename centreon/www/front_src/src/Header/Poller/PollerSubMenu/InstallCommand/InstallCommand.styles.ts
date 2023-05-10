import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { ThemeMode } from '@centreon/ui-context';

export const useStyles = makeStyles()((theme) => ({
  button: {
    '&:hover': {
      background: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.primary.dark
        : theme.palette.primary.light,
      color: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.common.white
        : theme.palette.primary.main
    }
  },
  hidden: {
    display: 'none'
  },
  text: {
    paddingLeft: theme.spacing(0.5)
  }
}));
