import { JSX } from 'react';

import { Box, Typography } from '@mui/material';
import { Header } from '@centreon/ui';

import { labelTooManyGraphsToDisplay } from './translatedLabels';
import { graphsCapNumber } from './constants';
import { useTooManyElementsCardStyles } from './TooManyElementsCard.styles';

interface Props {
  actions?: JSX.Element,
  listing: boolean,
  title: string
};

const TooManyElementsCard = ({actions, listing, title}: Props): JSX.Element => {
  const { classes } = useTooManyElementsCardStyles();

  return (
    <Box className={classes.container} height={listing ? 200 : 280}>
      <Header
        title={title}
        header={{
          displayTitle: true,
          extraComponent: actions
        }}
      />
      <Box className={
        listing ? classes.graphsCapMessageLisitng : classes.graphsCapMessage
      }>
        <Typography variant='h6'>
          {labelTooManyGraphsToDisplay(graphsCapNumber)}
        </Typography>
      </Box>
    </Box>
  );
};

export default TooManyElementsCard;
