import { Box, Typography } from '@mui/material';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

export const Warning = ({ label }: { label: string }): ReactElement => {
  const { t } = useTranslation();

  return (
    <Box className="bg-warning-light/50 rounded-sm p-2">
      <Typography>{t(label)}</Typography>
    </Box>
  );
};
