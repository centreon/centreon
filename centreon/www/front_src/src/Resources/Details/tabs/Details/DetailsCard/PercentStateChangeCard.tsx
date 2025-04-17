import { makeStyles } from 'tss-react/mui';

import { ResourceDetails } from '../../../models';

import FlappingChip from '../../../../Chip/Flapping';
import DetailsLine from './DetailsLine';

const useStyles = makeStyles()((theme) => ({
  percentStateCard: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'auto min-content'
  }
}));

interface Props {
  details: ResourceDetails;
}
const PercentStateChangeCard = ({ details }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.percentStateCard}>
      <DetailsLine
        line={`${Number(details.percent_state_change?.toFixed(3))}%`}
      />
      {details.flapping && <FlappingChip />}
    </div>
  );
};

export default PercentStateChangeCard;
