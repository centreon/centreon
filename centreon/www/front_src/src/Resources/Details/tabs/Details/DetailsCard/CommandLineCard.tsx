import IconCopyFile from '@mui/icons-material/FileCopy';
import { Card, Grid, IconButton, Tooltip, Typography } from '@mui/material';

import { useCopyToClipboard } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelCommand,
  labelCommandCopied,
  labelCopy,
  labelSomethingWentWrong
} from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';
import CommandWithArguments from '../CommandLine';

interface Props {
  details: ResourceDetails;
}

const useStyles = makeStyles()((theme) => ({
  commandLineCard: {
    padding: theme.spacing(1, 2, 2, 2)
  }
}));

const CommandLineCard = ({ details }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { copy } = useCopyToClipboard({
    errorMessage: t(labelSomethingWentWrong),
    successMessage: t(labelCommandCopied)
  });

  const copyCommandLine = (): Promise<void> =>
    copy(`sudo -u centreon-engine ${details.command_line}`);

  return (
    <Card className={classes.commandLineCard} elevation={0}>
      <Typography
        color="textSecondary"
        component="div"
        gutterBottom
        variant="body1"
      >
        <Grid alignItems="center" container spacing={1}>
          <Grid item>{t(labelCommand)}</Grid>
          <Grid item>
            <Tooltip onClick={copyCommandLine} title={labelCopy}>
              <IconButton data-testid={labelCopy} size="small">
                <IconCopyFile color="primary" fontSize="small" />
              </IconButton>
            </Tooltip>
          </Grid>
        </Grid>
      </Typography>
      <CommandWithArguments commandLine={details.command_line || ''} />
    </Card>
  );
};

export default CommandLineCard;
