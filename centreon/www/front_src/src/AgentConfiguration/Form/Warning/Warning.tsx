import { Box, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { AgentConfigurationForm, AgentType } from '../../models';
import {
  labelWarningEncryptionLevelCMA,
  labelWarningEncryptionLevelTelegraf
} from '../../translatedLabels';
import { useStyles } from './Warning.styles';

const EncryptionLevelWarning = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const { values } = useFormikContext<AgentConfigurationForm>();

  const label = equals(values?.type?.id, AgentType.Telegraf)
    ? labelWarningEncryptionLevelTelegraf
    : labelWarningEncryptionLevelCMA;

  return (
    <Box className={classes.warningBox}>
      <Typography>{t(label)}</Typography>
    </Box>
  );
};

export default EncryptionLevelWarning;
