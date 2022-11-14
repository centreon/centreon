import { makeStyles } from 'tss-react/mui';

import { Divider, Typography } from '@mui/material';

import {
  labelExcludedPeriods,
  labelSubTitleExclusionOfPeriods,
} from '../../../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: { margin: theme.spacing(0, 0, 2, 0) },
  divider: {},
}));

const AnomalyDetectionTitleExclusionPeriods = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <Typography variant="h6">{labelExcludedPeriods}</Typography>
      <Typography variant="caption">
        {labelSubTitleExclusionOfPeriods}
      </Typography>
      <Divider className={classes.divider} />
    </div>
  );
};

export default AnomalyDetectionTitleExclusionPeriods;
