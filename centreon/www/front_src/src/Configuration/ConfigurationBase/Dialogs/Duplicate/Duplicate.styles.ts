import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  duplicationCount: {
    display: 'flex',
    gap: theme.spacing(3),
    alignItems: 'center',
    marginTop: theme.spacing(1.5)
  },
  duplicationCountTitle: {
    fontWeight: theme.typography.fontWeightBold
  }
}));
