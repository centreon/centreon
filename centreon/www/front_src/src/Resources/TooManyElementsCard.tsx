import { Box, Typography } from '@mui/material';

import { Header } from '@centreon/ui';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Header } from '@centreon/ui';
import { Box, Typography } from '@mui/material';

import { graphsCapNumber } from './constants';
import { labelTooManyGraphsToDisplay } from './translatedLabels';

interface Props {
  actions?: ReactElement;
  listing: boolean;
  title: string;
}

const TooManyElementsCard = ({
  actions,
  listing,
  title
}: Props): ReactElement => {
  const { t } = useTranslation();

  const outerHeight = listing ? 200 : 280;

  return (
    <Box
      className="overflow-visible bg-white dark:bg-gray-900"
      height={outerHeight}
    >
      <Header
        header={{
          displayTitle: true,
          extraComponent: actions
        }}
        title={title}
      />
      <Box
        className={
          'flex items-center justify-center grow h-[calc(100%-16px)] text-gray-500'
        }
      >
        <Typography variant="h6">
          {t(labelTooManyGraphsToDisplay, { graphsCapNumber })}
        </Typography>
      </Box>
    </Box>
  );
};

export default TooManyElementsCard;
