import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  modalHeader: {
    fontSize: theme.typography.h5.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    marginBottom: theme.spacing(-3)
  }
}));
