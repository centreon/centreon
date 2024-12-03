import { Typography } from '@mui/material';
import { memo } from 'react';
import { labelNumerOfLines } from '../../translatedLabels';

const InformationsLine = ({ data }) => {
  return (
    <div
      style={{
        background: '#EDEDED',
        flex: 0.5,
        borderRadius: 8,
        justifyContent: 'center',
        display: 'flex',
        alignItems: 'center'
      }}
    >
      <Typography variant="body2" sx={{ fontWeight: 'bold' }}>
        {labelNumerOfLines}
      </Typography>
    </div>
  );
};

export default memo(InformationsLine);
