import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { ThemeMode } from '@centreon/ui-context';

export const useStyles = makeStyles()((theme) => ({
  list: {
    padding: 0
  },
  paper: {
    backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
      ? theme.palette.background.widget
      : theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    boxShadow: theme.shadows[3],
    marginLeft: theme.spacing(2),
    maxWidth: '350px'
  },
  popper: {
    zIndex: theme.zIndex.tooltip + 1
  }
}));
