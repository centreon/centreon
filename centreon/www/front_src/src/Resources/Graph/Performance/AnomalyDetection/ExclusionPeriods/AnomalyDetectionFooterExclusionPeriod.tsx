import { makeStyles } from 'tss-react/mui';

import { Button } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  confirmButton: {
    marginLeft: theme.spacing(2)
  },
  container: { margin: theme.spacing(0, 0, 2, 0) },
  footer: {
    display: 'flex',
    justifyContent: 'flex-end'
  }
}));

interface Props {
  cancelExclusionPeriod: () => void;
  confirmExcluderPeriods: () => void;
  dateExisted: boolean;
  isError: boolean;
}

const AnomalyDetectionFooterExclusionPeriod = ({
  confirmExcluderPeriods,
  isError,
  dateExisted,
  cancelExclusionPeriod
}: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.footer}>
      <Button
        data-testid="cancelExclusionPeriod"
        size="small"
        variant="text"
        onClick={cancelExclusionPeriod}
      >
        Cancel
      </Button>
      <Button
        className={classes.confirmButton}
        data-testid="confirmExclusionPeriods"
        disabled={isError || !dateExisted}
        size="small"
        variant="contained"
        onClick={confirmExcluderPeriods}
      >
        Confirm
      </Button>
    </div>
  );
};

export default AnomalyDetectionFooterExclusionPeriod;
