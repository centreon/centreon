import Typography from '@mui/material/Typography';
import { memo } from 'react';
import { useTranslation } from 'react-i18next';
import { labelWarningExportToCsv } from '../../translatedLabels';

const Warning = () => {
  const { t } = useTranslation();
  return (
    <Typography
      className="w-full bg-warning-light/50 p-2 rounded-sm"
      variant="body2"
    >
      {t(labelWarningExportToCsv)}
    </Typography>
  );
};

export default memo(Warning);
