import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useExportToCSV = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  warningBox: {
    backgroundColor: alpha(theme.palette.warning.main, 0.3),
    borderRadius: theme.shape.borderRadius,
    padding: theme.spacing(1.5)
  }
}));
