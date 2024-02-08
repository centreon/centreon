import { makeStyles } from 'tss-react/mui';

const useModalStyles = makeStyles()((theme) => ({
  modalTitle: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h5.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));

export default useModalStyles;
