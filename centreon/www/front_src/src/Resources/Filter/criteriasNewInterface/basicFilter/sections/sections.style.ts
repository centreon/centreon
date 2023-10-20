import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  containerFilter: {
    width: theme.spacing(75 / 2)
  },
  divider: {
    borderStyle: 'dashed',
    margin: theme.spacing(2, 0)
  },
  dividerInputs: {
    margin: theme.spacing(0.5, 0),
    opacity: 0
  }
}));
