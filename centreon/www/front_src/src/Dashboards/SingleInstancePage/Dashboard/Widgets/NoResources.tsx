import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { labelPreviewRemainsEmpty } from './translatedLabels';

const NoResources = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box
      sx={{
        alignItems: 'center',
        display: 'flex',
        height: '100%',
        justifyContent: 'center'
      }}
    >
      <Typography variant="h5">{t(labelPreviewRemainsEmpty)}</Typography>
    </Box>
  );
};

export default NoResources;
