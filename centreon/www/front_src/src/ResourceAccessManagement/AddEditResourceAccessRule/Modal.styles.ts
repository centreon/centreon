import { makeStyles } from 'tss-react/mui';

const useModalStyles = makeStyles()((theme) => ({
  modalBody: {
    padding: theme.spacing(2.5)
  },
  modalTitle: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h5.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));

export default useModalStyles;
