import { FormikValues, FormikErrors, FormikHelpers } from 'formik';

export interface StepComponentProps {
  disableNextOnSendingRequests: (sendingRequests: Array<boolean>) => void;
}

export interface Step {
  stepName: string;
  validate?: (values: FormikValues) => FormikErrors<FormikValues>;
  validationSchema?;
  Component: (props: StepComponentProps) => JSX.Element;
  hasActionsBar?: boolean;
  skipFormChangeCheck?: boolean;
}

interface ActionsBarLabels {
  labelPrevious: string;
  labelNext: string;
  labelFinish: string;
}

export interface WizardContentProps {
  sendingRequest: boolean;
  step: Step;
  isLastStep: boolean;
  isFirstStep: boolean;
  disableNextOnSendingRequests: (sendingRequests: Array<boolean>) => void;
  goToPreviousStep: () => void;
  goToNextStep: () => void;
  currentStep: number;
  actionsBarLabels: ActionsBarLabels;
}

interface ConfirmDialogLabels {
  labelTitle: string;
  labelMessage: string;
  labelCancel: string;
  labelConfirm: string;
}

export interface WizardProps {
  steps: Array<Step>;
  onSubmit?: (values: FormikValues, bag: FormikHelpers<FormikValues>) => void;
  initialValues?: FormikValues;
  fullHeight?: boolean;
  open: boolean;
  onClose?: () => void;
  width?: 'lg' | 'md' | 'sm' | 'xl' | 'xs' | false;
  confirmDialogLabels?: ConfirmDialogLabels;
  actionsBarLabels?: ActionsBarLabels;
}

export interface ActionsBarProps {
  isLastStep: boolean;
  isFirstStep: boolean;
  goToPreviousStep: () => void;
  goToNextStep: () => void;
  disableActionButtons: boolean;
  isSubmitting: boolean;
  submit: () => void;
  actionsBarLabels: ActionsBarLabels;
}
