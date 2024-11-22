import { Box, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import VaultForm from './Form/Form';
import { labelVaultConfiguration } from './translatedLabels';

const VaultConfiguration = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        gap: 2,
        alignItems: 'center'
      }}
    >
      <Typography variant="h5" sx={{ textAlign: 'center' }}>
        {t(labelVaultConfiguration)}
      </Typography>
      <VaultForm />
    </Box>
  );
};

export default VaultConfiguration;
