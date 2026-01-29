import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    boxShadow: theme.shadows[3],
    color: theme.palette.text.primary,
    maxWidth: 'none',
    padding: theme.spacing(1)
  }
}));
