import { Add as AddIcon } from '@mui/icons-material';
import { Box, Link } from '@mui/material';

import { Button } from '@centreon/ui/components';

import { JSX } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router';

import { labelCreateNewCMAToken } from '../../translatedLabels';

const tokensPageURL = '/administration/authentication-token?mode=edit&type=cma';

const RedirectToTokensPage = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box>
      <Button
        aria-label={t(labelCreateNewCMAToken)}
        icon={<AddIcon />}
        iconVariant="start"
        size="small"
        variant="ghost"
      >
        <Link
          component={RouterLink}
          sx={{
            all: 'unset'
          }}
          target="_blank"
          to={tokensPageURL}
        >
          {t(labelCreateNewCMAToken)}
        </Link>
      </Button>
    </Box>
  );
};

export default RedirectToTokensPage;
