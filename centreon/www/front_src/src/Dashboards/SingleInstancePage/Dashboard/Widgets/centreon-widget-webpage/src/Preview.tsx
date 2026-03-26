import { Box, Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelWebPagePreview } from './translatedLabels';
import { usePreviewStyles } from './useWebPage.styles';

const Preview = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = usePreviewStyles();

  return (
    <Box className={classes.container}>
      <Typography className={classes.label} variant="h6">
        {t(labelWebPagePreview)}
      </Typography>
    </Box>
  );
};

export default Preview;
