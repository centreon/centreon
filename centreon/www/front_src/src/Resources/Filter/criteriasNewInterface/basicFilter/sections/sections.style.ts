import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  basicInputs: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  containerFilter: {
    width: theme.spacing(75 / 2)
  },
  divider: {
    borderStyle: 'dashed',
    margin: theme.spacing(2, 0)
  }
}));
