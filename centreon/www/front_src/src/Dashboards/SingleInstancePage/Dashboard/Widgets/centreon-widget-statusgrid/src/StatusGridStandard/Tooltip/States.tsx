import { Box } from '@mui/material';

import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { ResourceData } from '../models';

import State from './State';

interface Props {
  data: ResourceData;
}

const States = ({ data }: Props): JSX.Element => {
  const { classes } = useHostTooltipContentStyles();

  return (
    <Box className={classes.servicesContainer}>
      {data.acknowledgementEndpoint && data.is_acknowledged && (
        <State endpoint={data.acknowledgementEndpoint} type="acknowledgement" />
      )}
      {data.downtimeEndpoint && data.is_in_downtime && (
        <State endpoint={data.downtimeEndpoint} type="downtime" />
      )}
    </Box>
  );
};

export default States;
