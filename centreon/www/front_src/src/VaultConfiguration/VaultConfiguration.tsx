import { Box, Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import VaultForm from './Form/Form';
import { labelVaultConfiguration } from './translatedLabels';

const VaultConfiguration = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box
      sx={{
        alignItems: 'center',
        display: 'flex',
        flexDirection: 'column',
        gap: 2
      }}
    >
      <Typography sx={{ textAlign: 'center' }} variant="h5">
        {t(labelVaultConfiguration)}
      </Typography>
      <VaultForm />
    </Box>
  );
};

export default VaultConfiguration;
