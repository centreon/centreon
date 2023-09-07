import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5)
  },
  iconButton: {
    padding: 0
  },
  text: {
    marginRight: theme.spacing(1.5)
  },
  tooltipClassName: {
    position: 'relative',
    top: theme.spacing(-0.5)
  }
}));
