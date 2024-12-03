import Typography from '@mui/material/Typography';
import { memo } from 'react';
import { labelWarningExportToCsv } from '../../translatedLabels';

const Warning = () => {
  return (
    <Typography
      style={{ width: '100%', backgroundColor: '#FCC481', padding: 8 }}
      variant="body2"
    >
      {labelWarningExportToCsv}
    </Typography>
  );
};

export default memo(Warning);
