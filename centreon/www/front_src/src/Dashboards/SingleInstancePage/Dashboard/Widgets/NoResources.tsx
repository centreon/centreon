import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { labelPreviewRemainsEmpty } from './translatedLabels';

const NoResources = ({ label }: { label?: string }): JSX.Element => {
  const { t } = useTranslation();

  const labelPreview = label || labelPreviewRemainsEmpty;

  return (
    <Box
      sx={{
        alignItems: 'center',
        display: 'flex',
        height: '100%',
        justifyContent: 'center'
      }}
    >
      <Typography variant="h5">{t(labelPreview)}</Typography>
    </Box>
  );
};

export default NoResources;
