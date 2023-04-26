import { useState } from 'react';

import { equals, length, dec, pipe, inc, filter, isEmpty, not } from 'ramda';
import { Formik } from 'formik';
import { makeStyles } from 'tss-react/mui';

import { Dialog, DialogContent } from '@mui/material';

import Confirm from '../Dialog/Confirm';

import { WizardProps } from './models';
import Stepper from './Stepper';
import WizardContent from './WizardContent';

const useStyles = makeStyles()(() => ({
  dialogContent: {
    display: 'flex',
    padding: 0
  },
  fullHeight: {
    height: '100%'
  }
}));

const actionsBarLabelsDefaultValues = {
  labelFinish: 'Finish',
  labelNext: 'Next',
  labelPrevious: 'Previous'
};

const Wizard = ({
  steps,
  onSubmit = undefined,
  initialValues = {},
  width = 'sm',
  fullHeight = false,
  open,
  onClose = undefined,
  confirmDialogLabels = undefined,
  actionsBarLabels = actionsBarLabelsDefaultValues
}: WizardProps): JSX.Element => {
  const { classes } = useStyles();
  const [currentStep, setCurrentStep] = useState(0);
  const [sendingRequest, setSendingRequest] = useState(false);
  const [openConfirm, setOpenConfirm] = useState(false);

  const isLastStep = pipe(dec, equals(currentStep))(length(steps));

  const isFirstStep = equals(currentStep, 0);

  const goToNextStep = (): void => {
    if (isLastStep) {
      return;
    }
    setCurrentStep(inc(currentStep));
  };

  const goToPreviousStep = (): void => {
    if (isFirstStep) {
      return;
    }
    setCurrentStep(dec(currentStep));
  };

  const disableNextOnSendingRequests = (sendingRequests): void => {
    setSendingRequest(
      pipe(isEmpty, not)(filter(equals(true), sendingRequests))
    );
  };

  const submit = (values, bag): void => {
    if (isLastStep && onSubmit) {
      onSubmit(values, bag);

      return;
    }

    bag.setSubmitting(false);
  };

  const handleClose = (_, reason): void => {
    if (equals(reason, 'backdropClick')) {
      setOpenConfirm(true);

      return;
    }
    onClose?.();
  };

  const handleCloseConfirm = (confirm): void => {
    setOpenConfirm(false);

    if (!confirm) {
      return;
    }

    onClose?.();
  };

  const { validate, validationSchema } = steps[currentStep];

  return (
    <>
      <Dialog
        fullWidth
        classes={{
          paper: fullHeight ? classes.fullHeight : undefined
        }}
        maxWidth={width}
        open={open}
        onClose={handleClose}
      >
        <Stepper currentStep={currentStep} steps={steps} />
        <Formik
          validateOnChange
          initialValues={initialValues}
          validate={validate}
          validateOnBlur={false}
          validationSchema={validationSchema}
          onSubmit={submit}
        >
          <DialogContent className={classes.dialogContent}>
            <WizardContent
              actionsBarLabels={actionsBarLabels}
              currentStep={currentStep}
              disableNextOnSendingRequests={disableNextOnSendingRequests}
              goToNextStep={goToNextStep}
              goToPreviousStep={goToPreviousStep}
              isFirstStep={isFirstStep}
              isLastStep={isLastStep}
              sendingRequest={sendingRequest}
              step={steps[currentStep]}
            />
          </DialogContent>
        </Formik>
      </Dialog>
      <Confirm
        open={openConfirm}
        onCancel={(): void => handleCloseConfirm(false)}
        onConfirm={(): void => handleCloseConfirm(true)}
        {...confirmDialogLabels}
      />
    </>
  );
};

export default Wizard;
