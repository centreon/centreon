import { Stepper as MUIStepper, Step, StepLabel } from '@mui/material';

import { gte, length } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import type { Step as StepType } from './models';
import StepIcon from './StepIcon';

interface Props {
  currentStep: number;
  steps: Array<StepType>;
}

const useStyles = makeStyles()((theme) => ({
  dialogTitle: {
    padding: theme.spacing(0)
  },
  label: {
    '& .MuiStepLabel-alternativeLabel': {
      fontSize: '0.8rem',
      marginTop: '4px'
    }
  },
  stepper: {
    padding: theme.spacing(2)
  }
}));

const Stepper = ({ steps, currentStep }: Props): JSX.Element | null => {
  const { classes } = useStyles();

  if (gte(1, length(steps))) {
    return null;
  }

  return (
    <MUIStepper
      activeStep={currentStep}
      alternativeLabel
      className={classes.stepper}
    >
      {steps.map(({ stepName }) => (
        <Step key={stepName}>
          <StepLabel
            classes={{
              alternativeLabel: classes.label
            }}
            StepIconComponent={StepIcon}
          >
            {stepName}
          </StepLabel>
        </Step>
      ))}
    </MUIStepper>
  );
};

export default Stepper;
