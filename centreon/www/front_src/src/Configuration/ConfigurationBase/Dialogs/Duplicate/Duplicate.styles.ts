import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  duplicationCount: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(3),
    marginTop: theme.spacing(1.5)
  },
  duplicationCountTitle: {
    fontWeight: theme.typography.fontWeightBold
  }
}));
