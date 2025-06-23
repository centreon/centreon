import { alpha } from '@mui/system';
import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  warningBox: {
    backgroundColor: alpha(theme.palette.warning.main, 0.3),
    borderRadius: theme.shape.borderRadius,
    padding: theme.spacing(1.5)
  }
}));
