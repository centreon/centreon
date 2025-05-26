import Typography from '@mui/material/Typography';
import { memo } from 'react';
import { useTranslation } from 'react-i18next';
import { labelWarningExportToCsv } from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';

const Warning = () => {
  const { classes } = useExportCsvStyles();
  const { t } = useTranslation();
  return (
    <Typography className={classes.warning} variant="body2">
      {t(labelWarningExportToCsv)}
    </Typography>
  );
};

export default memo(Warning);
