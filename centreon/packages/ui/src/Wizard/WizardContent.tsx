import { useEffect } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import ActionsBar from './ActionsBar';
import { WizardContentProps } from './models';

const useStyles = makeStyles()(() => ({
  content: {
    height: '100%',
    overflow: 'auto'
  },
  form: {
    display: 'flex',
    flex: 1,
    flexDirection: 'column'
  }
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
  goToNextStep
}: WizardContentProps): JSX.Element => {
  const { classes } = useStyles();
  const { isSubmitting, isValid, dirty, handleSubmit, validateForm } =
    useFormikContext();

  const { Component, hasActionsBar = true, skipFormChangeCheck } = step;

  const getFormChanged = (): boolean =>
    equals(true, skipFormChangeCheck) ? false : !dirty;

  const submit = (): void => {
    handleSubmit();
  };

  useEffect(() => {
    validateForm();
  }, [currentStep]);

  const disableActionButtons =
    sendingRequest || isSubmitting || !isValid || getFormChanged();

  return (
    <form className={classes.form} onSubmit={handleSubmit}>
      <div className={classes.content}>
        <Component
          disableNextOnSendingRequests={disableNextOnSendingRequests}
        />
      </div>
      {hasActionsBar && (
        <ActionsBar
          actionsBarLabels={actionsBarLabels}
          disableActionButtons={disableActionButtons}
          goToNextStep={goToNextStep}
          goToPreviousStep={goToPreviousStep}
          isFirstStep={isFirstStep}
          isLastStep={isLastStep}
          isSubmitting={isSubmitting}
          submit={submit}
        />
      )}
    </form>
  );
};

export default WizardContent;
