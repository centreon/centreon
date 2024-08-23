import { useState } from 'react';

import { Formik } from 'formik';
import { dec, equals, filter, inc, isEmpty, length, not, pipe } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Dialog, DialogContent } from '@mui/material';

import Confirm from '../Dialog/Confirm';

import Stepper from './Stepper';
import WizardContent from './WizardContent';
import { WizardProps } from './models';

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
  classNameDialogContent,
  steps,
  onSubmit = undefined,
  initialValues = {},
  width = 'sm',
  fullHeight = false,
  open,
  onClose = undefined,
  confirmDialogLabels = undefined,
  actionsBarLabels = actionsBarLabelsDefaultValues,
  displayConfirmDialog,
  ...rest
}: WizardProps): JSX.Element => {
  const { classes, cx } = useStyles();
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

  const controlDisplayConfirmationDialog = (): void => {
    if (!equals(displayConfirmDialog, false)) {
      setOpenConfirm(displayConfirmDialog ?? true);

      return;
    }
    onClose?.();
  };

  const handleClose = (_, reason): void => {
    if (equals(reason, 'backdropClick')) {
      controlDisplayConfirmationDialog();

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
        data-testid="Dialog"
        maxWidth={width}
        open={open}
        onClose={handleClose}
        {...rest}
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
          <DialogContent
            className={cx(classes.dialogContent, classNameDialogContent)}
          >
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
