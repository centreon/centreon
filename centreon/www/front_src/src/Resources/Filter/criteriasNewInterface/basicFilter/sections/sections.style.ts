import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  basicInputs: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  containerFilter: {
    width: '100%'
  },
  divider: {
    borderStyle: 'dashed',
    margin: theme.spacing(2, 0)
  },
  input: {
    maxWidth: theme.spacing(40)
  }
}));
