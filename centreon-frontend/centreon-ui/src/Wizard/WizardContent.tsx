import * as React from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';

import { makeStyles } from '@material-ui/core';

import { WizardContentProps } from './models';
import ActionsBar from './ActionsBar';

const useStyles = makeStyles((theme) => ({
  form: {
    display: 'flex',
    flexDirection: 'column',
    flex: 1,
  },
  content: {
    height: '100%',
    overflow: 'auto',
    padding: theme.spacing(0, 3, 1, 3),
  },
}));

const WizardContent = ({
  sendingRequest,
  step,
  isLastStep,
  isFirstStep,
  disableNextOnSendingRequests,
  goToPreviousStep,
  currentStep,
  actionsBarLabels,
  goToNextStep,
}: WizardContentProps): JSX.Element => {
  const classes = useStyles();
  const {
    isSubmitting,
    isValid,
    dirty,
    handleSubmit,
    validateForm,
  } = useFormikContext();

  const { Component, hasActionsBar = true, skipFormChangeCheck } = step;

  const getFormChanged = () =>
    equals(true, skipFormChangeCheck) ? false : !dirty;

  const submit = (): void => {
    handleSubmit();
  };

  React.useEffect(() => {
    validateForm();
  }, [currentStep]);

  const disableActionButtons =
    sendingRequest || isSubmitting || !isValid || getFormChanged();

  return (
    <form onSubmit={handleSubmit} className={classes.form}>
      <div className={classes.content}>
        <Component
          disableNextOnSendingRequests={disableNextOnSendingRequests}
        />
      </div>
      {hasActionsBar && (
        <ActionsBar
          isFirstStep={isFirstStep}
          isLastStep={isLastStep}
          goToPreviousStep={goToPreviousStep}
          submit={submit}
          disableActionButtons={disableActionButtons}
          isSubmitting={isSubmitting}
          actionsBarLabels={actionsBarLabels}
          goToNextStep={goToNextStep}
        />
      )}
    </form>
  );
};

export default WizardContent;
