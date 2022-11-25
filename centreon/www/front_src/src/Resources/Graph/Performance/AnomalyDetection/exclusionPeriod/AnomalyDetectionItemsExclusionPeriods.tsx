import dayjs from 'dayjs';
import { useAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import DeleteIcon from '@mui/icons-material/Delete';
import { IconButton, Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { thresholdsAnomalyDetectionDataAtom } from '../anomalyDetectionAtom';

const useStyles = makeStyles()(() => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  date: {
    fontSize: 13,
    fontWeight: 'bold',
  },
  deleteIcon: {
    padding: 0,
  },
}));

const AnomalyDetectionItemsExclusionPeriods = ({ item }: any): JSX.Element => {
  const { classes } = useStyles();

  const [thresholdsAnomalyDetectionData, setThresholdAnomalyDetectionData] =
    useAtom(thresholdsAnomalyDetectionDataAtom);
  const { format } = useLocaleDateTimeFormat();

  const deletePeriod = (): void => {
    const newData =
      thresholdsAnomalyDetectionData.exclusionPeriodsThreshold.data.filter(
        (element) => {
          if (
            dayjs(element.id.endDate).isSame(dayjs(item?.endDate)) &&
            dayjs(element.id.startDate).isSame(dayjs(item?.startDate))
          ) {
            return null;
          }

          return item;
        },
      );

    setThresholdAnomalyDetectionData({
      ...thresholdsAnomalyDetectionData,
      exclusionPeriodsThreshold: {
        ...thresholdsAnomalyDetectionData.exclusionPeriodsThreshold,
        data: [...newData],
      },
    });
  };

  return (
    <div className={classes.container}>
      <Typography variant="subtitle2">From</Typography>
      <Typography className={classes.date}>
        {format({ date: item.startDate, formatString: 'L LTS' })}
      </Typography>
      <Typography>To</Typography>
      <Typography className={classes.date}>
        {format({ date: item.endDate, formatString: 'L LTS' })}
      </Typography>
      <IconButton
        aria-label="delete"
        className={classes.deleteIcon}
        onClick={deletePeriod}
      >
        <DeleteIcon />
      </IconButton>
    </div>
  );
};

export default AnomalyDetectionItemsExclusionPeriods;
