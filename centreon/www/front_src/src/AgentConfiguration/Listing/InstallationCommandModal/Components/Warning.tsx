import { Box, Typography } from '@mui/material';
import { ReactElement } from 'react';

interface Props {
  label: string;
}

export const Warning = ({ label }: Props): ReactElement => {
  return (
    <Box className="bg-warning-light/50 rounded-sm p-2">
      <Typography>{label}</Typography>
    </Box>
  );
};
