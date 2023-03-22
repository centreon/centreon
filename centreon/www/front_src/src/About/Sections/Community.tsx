import { useTranslation } from 'react-i18next';

import { Link, Typography } from '@mui/material';
import { Box } from '@mui/system';

import {
  labelAnd,
  labelCentreon,
  labelDevelopedBy,
  labelCommunity,
  labelCentreonWebsite
} from '../translatedLabels';

const Community = (): JSX.Element => {
  const { t } = useTranslation();

  const linkProps = {
    rel: 'noreferrer noopener',
    target: '_blank'
  };

  return (
    <Box sx={{ alignSelf: 'flex-end' }}>
      <Typography>
        {t(labelDevelopedBy)}{' '}
        <Link
          href="https://www.centreon.com"
          underline="hover"
          {...linkProps}
          aria-label={t(labelCentreonWebsite)}
        >
          {t(labelCentreon)}
        </Link>{' '}
        {t(labelAnd)}{' '}
        <Link
          aria-label={t(labelCommunity)}
          href="https://thewatch.centreon.com/"
          underline="hover"
          {...linkProps}
        >
          {t(labelCommunity)}
        </Link>
      </Typography>
    </Box>
  );
};

export default Community;
