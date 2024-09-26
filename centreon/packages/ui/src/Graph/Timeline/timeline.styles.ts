import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative',
    left: theme.spacing(-1)
  }
}));
