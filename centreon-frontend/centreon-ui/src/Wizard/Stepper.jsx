import React from 'react';

import PropTypes from 'prop-types';

import { makeStyles } from '@material-ui/core/styles';
import MaterialStepper from '@material-ui/core/Stepper';
import Step from '@material-ui/core/Step';
import StepLabel from '@material-ui/core/StepLabel';

import StepIcon from './StepIcon';

const useStyles = makeStyles((theme) => ({
  stepper: {
    padding: '18px 16px 14px 16px',
    backgroundColor: theme.palette.grey[200],
  },
  label: {
    '& .MuiStepLabel-alternativeLabel': {
      marginTop: '4px',
      fontSize: '0.8rem',
    },
  },
}));

const Stepper = ({ activeStep, children }) => {
  const classes = useStyles();

  return (
    <MaterialStepper
      className={classes.stepper}
      alternativeLabel
      activeStep={activeStep}
    >
      {React.Children.toArray(children).map((child, index) => (
        <Step key={child.props.label || index}>
          <StepLabel
            classes={{
              alternativeLabel: classes.label,
            }}
            StepIconComponent={StepIcon}
          >
            {child.props.label ? child.props.label : null}
          </StepLabel>
        </Step>
      ))}
    </MaterialStepper>
  );
};

Stepper.propTypes = {
  activeStep: PropTypes.number,
  children: PropTypes.node.isRequired,
};

Stepper.defaultProps = {
  activeStep: null,
};

export default Stepper;
