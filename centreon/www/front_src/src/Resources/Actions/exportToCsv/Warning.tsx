import Typography from '@mui/material/Typography';
import { memo } from 'react';
import { labelWarningExportToCsv } from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';

const Warning = () => {
  const { classes } = useExportCsvStyles();
  return (
    <Typography className={classes.warning} variant="body2">
      {labelWarningExportToCsv}
    </Typography>
  );
};

export default memo(Warning);
