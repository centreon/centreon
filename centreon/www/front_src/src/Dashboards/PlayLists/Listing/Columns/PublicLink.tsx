import { useTranslation } from 'react-i18next';

import LinkIcon from '@mui/icons-material/Link';
import { Box, Typography } from '@mui/material';

import {
  ComponentColumnProps,
  IconButton,
  useCopyToClipboard
} from '@centreon/ui';

import {
  labelCopyLink,
  labelCopyLinkTooltip,
  labelFailedToCopyTheLink,
  labelLinkHasBeenCopied
} from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles.styles';

const Tooltip = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  return (
    <Box>
      <Typography className={classes.copyLink} variant="body1">
        {t(labelCopyLink)}
      </Typography>
      <Typography variant="body2">{t(labelCopyLinkTooltip)}</Typography>
    </Box>
  );
};

const PublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const { isPublic, publicLink } = row;

  const { copy } = useCopyToClipboard({
    errorMessage: labelFailedToCopyTheLink,
    successMessage: labelLinkHasBeenCopied
  });

  return (
    <IconButton
      disabled={!isPublic}
      title={isPublic && <Tooltip />}
      onClick={() => copy(publicLink)}
    >
      <LinkIcon />
    </IconButton>
  );
};

export default PublicLink;
