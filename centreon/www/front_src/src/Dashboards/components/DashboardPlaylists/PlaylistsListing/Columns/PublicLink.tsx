import { useTranslation } from 'react-i18next';
import { isNil } from 'ramda';

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

import { useColumnStyles } from './useColumnStyles';

const Tooltip = ({ copyLink }: { copyLink: () => void }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  return (
    <Box>
      <Typography
        className={classes.copyLink}
        variant="body1"
        onClick={copyLink}
      >
        {t(labelCopyLink)}
      </Typography>
      <Typography variant="body2">{t(labelCopyLinkTooltip)}</Typography>
    </Box>
  );
};

const PublicLink = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();

  const { isPublic, publicLink } = row;

  const isNestedRow = !isNil(row?.role);

  const { copy } = useCopyToClipboard({
    errorMessage: labelFailedToCopyTheLink,
    successMessage: labelLinkHasBeenCopied
  });

  const copyThePublicLink = (): void => {
    copy(publicLink);
  };

  if (!isNestedRow) {
    return (
      <IconButton
        disabled={!isPublic}
        title={isPublic && <Tooltip copyLink={copyThePublicLink} />}
        onClick={copyThePublicLink}
      >
        <LinkIcon className={classes.linkIcon} />
      </IconButton>
    );
  }

  return <Box />;
};

export default PublicLink;
