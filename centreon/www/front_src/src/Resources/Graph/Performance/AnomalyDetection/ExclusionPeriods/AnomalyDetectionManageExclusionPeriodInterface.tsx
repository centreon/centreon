import { makeStyles } from 'tss-react/mui';

import { Button, Divider, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import {
  labelExclusionOfPeriods,
  labelSubTitleExclusionOfPeriods,
  labelButtonExcludeAPeriod
} from '../../../../translatedLabels';
import { ExclusionPeriodsThreshold } from '../models';

import AnomalyDetectionItemsToExclude from './AnomalyDetectionItemsToExclude';

const useStyles = makeStyles()((theme) => ({
  body: {
    display: 'flex',
    justifyContent: 'center',
    marginTop: theme.spacing(5)
  },
  divider: {
    margin: theme.spacing(0, 2)
  },
  exclusionButton: {
    width: theme.spacing(22.5)
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'column'
  }
}));

interface Props {
  data: ExclusionPeriodsThreshold['data'];
  enabledExclusionButton: boolean;
  excludeAPeriod: () => void;
}
const AnomalyDetectionManageExclusionPeriodInterface = ({
  excludeAPeriod,
  enabledExclusionButton,
  data
}: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <>
      <div className={classes.subContainer}>
        <Typography data-testid={labelExclusionOfPeriods} variant="h6">
          {labelExclusionOfPeriods}
        </Typography>
        <Typography
          data-testid={labelSubTitleExclusionOfPeriods}
          variant="caption"
        >
          {labelSubTitleExclusionOfPeriods}
        </Typography>
        <div className={classes.body}>
          <Button
            className={classes.exclusionButton}
            data-testid={labelButtonExcludeAPeriod}
            disabled={enabledExclusionButton}
            size="small"
            startIcon={<AddIcon />}
            variant="contained"
            onClick={excludeAPeriod}
          >
            {labelButtonExcludeAPeriod}
          </Button>
        </div>
      </div>
      <Divider flexItem className={classes.divider} orientation="vertical" />
      <AnomalyDetectionItemsToExclude data={data} />
    </>
  );
};

export default AnomalyDetectionManageExclusionPeriodInterface;
