import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { WidgetPropertyProps } from '../../models';

const Warning = ({ label }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box className="bg-warning-light/50 rounded-sm p-2">
      <Typography>{t(label)}</Typography>
    </Box>
  );
};

export default Warning;
