import { Dialog, DialogContent } from '@mui/material';

import { Formik } from 'formik';
import { dec, equals, filter, inc, isEmpty, length, not, pipe } from 'ramda';
import { useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import Confirm from '../Dialog/Confirm';
import type { WizardProps } from './models';
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
        classes={{
          paper: fullHeight ? classes.fullHeight : undefined
        }}
        data-testid="Dialog"
        fullWidth
        maxWidth={width}
        onClose={handleClose}
        open={open}
        {...rest}
      >
        <Stepper currentStep={currentStep} steps={steps} />
        <Formik
          initialValues={initialValues}
          onSubmit={submit}
          validate={validate}
          validateOnBlur={false}
          validateOnChange
          validationSchema={validationSchema}
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
        onCancel={(): void => handleCloseConfirm(false)}
        onConfirm={(): void => handleCloseConfirm(true)}
        open={openConfirm}
        {...confirmDialogLabels}
      />
    </>
  );
};

export default Wizard;
