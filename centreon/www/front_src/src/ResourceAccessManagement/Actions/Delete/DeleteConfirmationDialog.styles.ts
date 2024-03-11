import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

const useDeleteConfirmationDialogStyles = makeStyles()((theme) => ({
  confimButton: {
    '&:hover': {
      background: alpha(theme.palette.error.main, 0.8)
    },
    background: theme.palette.error.main,
    color: theme.palette.common.white
  },
  paper: {
    width: theme.spacing(60)
  }
}));

export default useDeleteConfirmationDialogStyles;
