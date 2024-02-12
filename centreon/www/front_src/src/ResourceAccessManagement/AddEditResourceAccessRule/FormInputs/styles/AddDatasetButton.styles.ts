import { makeStyles } from 'tss-react/mui';

export const useAddDatasetButtonStyles = makeStyles()((theme) => ({
  addDatasetButtonAvatar: {
    backgroundColor: theme.palette.primary.main
  },
  addDatasetButtonChip: {
    borderColor: theme.palette.common.white,
    borderRadius: theme.spacing(2),
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2),
    height: theme.spacing(4)
  },
  addDatasetButtonDivider: {
    width: '95%'
  },
  addDatasetButtonIcon: {
    color: theme.palette.common.white
  }
}));
