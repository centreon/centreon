import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { labelWebPagePreview } from './translatedLabels';
import { usePreviewStyles } from './useWebPage.styles';

const Preview = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = usePreviewStyles();

  return (
    <Box className={classes.container}>
      <Typography variant="h6" className={classes.label}>
        {t(labelWebPagePreview)}
      </Typography>
    </Box>
  );
};

export default Preview;
