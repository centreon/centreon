import { Button, CircularProgress, Grid, Typography } from '@mui/material';

import { makeStyles } from 'tss-react/mui';

import type { ActionsBarProps } from './models';

const useStyles = makeStyles()((theme) => ({
  container: {
    bottom: 0,
    padding: theme.spacing(1, 3),
    position: 'sticky'
  },
  loader: {
    marginLeft: theme.spacing(1)
  }
}));

const ActionsBar = ({
  isFirstStep,
  goToPreviousStep,
  goToNextStep,
  submit,
  disableActionButtons,
  isLastStep,
  isSubmitting,
  actionsBarLabels
}: ActionsBarProps): JSX.Element => {
  const { classes } = useStyles();

  const preventEnterKey = (keyEvent: React.KeyboardEvent): void => {
    if ((keyEvent.charCode || keyEvent.keyCode) === 13) {
      keyEvent.preventDefault();
    }
  };

  const { labelFinish, labelNext, labelPrevious } = actionsBarLabels;

  const labelNextFinish = isLastStep ? labelFinish : labelNext;

  return (
    <Grid
      className={classes.container}
      container
      direction="row"
      sx={{
        alignItems: 'center',
        justifyContent: 'flex-end'
      }}
    >
      <Grid>
        {!isFirstStep && (
          <Button
            aria-label={labelPrevious}
            onClick={goToPreviousStep}
            onKeyPress={preventEnterKey}
          >
            <Typography>{labelPrevious}</Typography>
          </Button>
        )}
      </Grid>
      <Grid>
        <Button
          aria-label={labelNextFinish}
          color="primary"
          disabled={disableActionButtons}
          onClick={(): void => (isLastStep ? submit() : goToNextStep())}
          onKeyPress={preventEnterKey}
        >
          <Typography>{labelNextFinish}</Typography>
          {isSubmitting && (
            <CircularProgress className={classes.loader} size={20} />
          )}
        </Button>
      </Grid>
    </Grid>
  );
};

export default ActionsBar;
