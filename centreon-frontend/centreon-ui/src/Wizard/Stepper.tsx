import * as React from 'react';

import { length, gte } from 'ramda';

import {
  Stepper as MUIStepper,
  Step,
  StepLabel,
  makeStyles,
} from '@material-ui/core';

import { Step as StepType } from './models';
import StepIcon from './StepIcon';

interface Props {
  steps: Array<StepType>;
  currentStep: number;
}

const useStyles = makeStyles((theme) => ({
  stepper: {
    padding: theme.spacing(2),
    backgroundColor: theme.palette.grey[200],
  },
  label: {
    '& .MuiStepLabel-alternativeLabel': {
      marginTop: '4px',
      fontSize: '0.8rem',
    },
  },
  dialogTitle: {
    padding: theme.spacing(0),
  },
}));

const Stepper = ({ steps, currentStep }: Props): JSX.Element | null => {
  const classes = useStyles();

  if (gte(1, length(steps))) {
    return null;
  }

  return (
    <MUIStepper
      alternativeLabel
      activeStep={currentStep}
      className={classes.stepper}
    >
      {steps.map(({ stepName }) => (
        <Step key={stepName}>
          <StepLabel
            classes={{
              alternativeLabel: classes.label,
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
