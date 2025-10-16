import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';
import { Header } from '@centreon/ui';

import { labelTooManyGraphsToDisplay } from './translatedLabels';
import { graphsCapNumber } from './constants';

interface Props {
  actions?: JSX.Element,
  listing: boolean,
  title: string
};

const TooManyElementsCard = ({actions, listing, title}: Props): JSX.Element => {
  const { t } = useTranslation();

  const outerHeight = listing ? 200 : 280;

  return (
    <Box
      className='overflow-visible bg-white dark:bg-gray-900'
      height={outerHeight}
    >
      <Header
        title={title}
        header={{
          displayTitle: true,
          extraComponent: actions
        }}
      />
      <Box className={
        `flex items-center justify-center grow h-[calc(100%-16px)] text-gray-500`
      }>
        <Typography variant='h6'>
          {t(labelTooManyGraphsToDisplay, { graphsCapNumber })}
        </Typography>
      </Box>
    </Box>
  );
};

export default TooManyElementsCard;
