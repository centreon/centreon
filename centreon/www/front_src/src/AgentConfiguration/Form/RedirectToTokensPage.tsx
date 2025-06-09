import { Add as AddIcon } from '@mui/icons-material';

import { Button } from '@centreon/ui/components';
import { Box, Link } from '@mui/material';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router';
import { labelCreateNewCMAToken } from '../translatedLabels';

const tokensPageURL = '/administration/authentication-token?mode=edit&type=cma';

const RedirectToTokensPage = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Box className="-mt-4">
      <Button
        icon={<AddIcon />}
        iconVariant="start"
        aria-label={t(labelCreateNewCMAToken)}
        variant="ghost"
        size="small"
      >
        <Link
          sx={{
            all: 'unset'
          }}
          component={RouterLink}
          to={tokensPageURL}
          target="_blank"
        >
          {t(labelCreateNewCMAToken)}
        </Link>
      </Button>
    </Box>
  );
};

export default RedirectToTokensPage;
