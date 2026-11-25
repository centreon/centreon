import { Typography } from '@mui/material';

import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { labelReasoningHint } from '../translatedLabels';

// under both models' reasoning settings, across the section: it applies to both
const ReasoningHint = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Typography
      color="textSecondary"
      data-testid="reasoningHint"
      variant="caption"
    >
      {t(labelReasoningHint)}
    </Typography>
  );
};

export default ReasoningHint;
