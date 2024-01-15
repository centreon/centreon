import { makeStyles } from 'tss-react/mui';

export const useDeleteDatasetButtonStyles = makeStyles()((theme) => ({
  deleteDatasetButtonContainer: {
    marginBottom: theme.spacing(6.5),
    marginTop: theme.spacing(0.5)
  },
  deleteIcon: {
    color: theme.palette.common.white,
    margin: '0px'
  }
}));
