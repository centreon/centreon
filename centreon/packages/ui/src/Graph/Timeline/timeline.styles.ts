import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: theme.spacing(1),
    boxShadow: theme.shadows[3],
    maxWidth: 'none'
  }
}));
