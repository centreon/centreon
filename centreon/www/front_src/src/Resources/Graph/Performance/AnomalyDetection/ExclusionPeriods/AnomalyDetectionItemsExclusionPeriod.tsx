import { useAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import dayjs from 'dayjs';

import DeleteIcon from '@mui/icons-material/Delete';
import { IconButton, Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { exclusionPeriodsThresholdAtom } from '../anomalyDetectionAtom';
import { SelectedDateToDelete } from '../models';

const useStyles = makeStyles()(() => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between'
  },
  date: {
    fontSize: 13,
    fontWeight: 'bold'
  },
  deleteIcon: {
    padding: 0
  }
}));

interface Props {
  item: SelectedDateToDelete;
  onDeleteExcludePeriod?: () => void;
}

const AnomalyDetectionItemsExclusionPeriod = ({
  item,
  onDeleteExcludePeriod
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [exclusionPeriodsThreshold, setExclusionPeriodsThreshold] = useAtom(
    exclusionPeriodsThresholdAtom
  );
  const { format } = useLocaleDateTimeFormat();
  const { end, start } = item;

  // call api of deletion
  const deletePeriod = (): void => {
    const newData = exclusionPeriodsThreshold.data.filter((element) => {
      if (
        dayjs(element.id.end).isSame(dayjs(end)) &&
        dayjs(element.id.start).isSame(dayjs(start))
      ) {
        return null;
      }

      return item;
    });
    setExclusionPeriodsThreshold({
      data: newData
    });
  };

  return (
    <div className={classes.container}>
      <Typography variant="subtitle2">From</Typography>
      <Typography className={classes.date}>
        {format({ date: start, formatString: 'L LTS' })}
      </Typography>
      <Typography>To</Typography>
      <Typography className={classes.date}>
        {format({ date: end, formatString: 'L LTS' })}
      </Typography>
      <IconButton
        aria-label="delete"
        className={classes.deleteIcon}
        onClick={deletePeriod}
        // onClick={onDeleteExcludePeriod}
      >
        <DeleteIcon />
      </IconButton>
    </div>
  );
};

export default AnomalyDetectionItemsExclusionPeriod;
