import { makeStyles } from 'tss-react/mui';

export const useAddDatasetButtonStyles = makeStyles()((theme) => ({
  addDatasetButton: {
    borderRadius: theme.spacing(2),
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2),
    height: theme.spacing(4),
    paddingRight: theme.spacing(1)
  },
  addDatasetButtonDivider: {
    width: '95%'
  }
}));
