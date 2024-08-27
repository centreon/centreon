import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  confirmButton: {
    color: theme.palette.common.white
  },
  labelMessage: {
    color: theme.palette.common.black
  },
  title: {
    color: theme.palette.primary.main,
    padding: theme.spacing(1, 3, 0, 1.5)
  }
}));
