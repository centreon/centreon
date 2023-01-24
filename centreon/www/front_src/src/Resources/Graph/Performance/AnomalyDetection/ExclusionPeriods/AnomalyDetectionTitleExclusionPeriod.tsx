import { makeStyles } from 'tss-react/mui';

import { Divider, Typography } from '@mui/material';

import {
  labelExcludedPeriods,
  labelSubTitleExclusionOfPeriods
} from '../../../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: { margin: theme.spacing(0, 0, 3, 0) },
  divider: {
    borderColor: theme.palette.text.secondary,
    marginTop: theme.spacing(1)
  }
}));

const AnomalyDetectionTitleExclusionPeriod = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <Typography data-testid={labelExcludedPeriods} variant="h6">
        {labelExcludedPeriods}
      </Typography>
      <Typography variant="caption">
        {labelSubTitleExclusionOfPeriods}
      </Typography>
      <Divider className={classes.divider} variant="fullWidth" />
    </div>
  );
};

export default AnomalyDetectionTitleExclusionPeriod;
