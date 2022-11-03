import { FormikValues, FormikErrors, FormikHelpers } from 'formik';

export interface StepComponentProps {
  disableNextOnSendingRequests: (sendingRequests: Array<boolean>) => void;
}

export interface Step {
  Component: (props: StepComponentProps) => JSX.Element;
  hasActionsBar?: boolean;
  skipFormChangeCheck?: boolean;
  stepName: string;
  validate?: (values: FormikValues) => FormikErrors<FormikValues>;
  validationSchema?;
}

interface ActionsBarLabels {
  labelFinish: string;
  labelNext: string;
  labelPrevious: string;
}

export interface WizardContentProps {
  actionsBarLabels: ActionsBarLabels;
  currentStep: number;
  disableNextOnSendingRequests: (sendingRequests: Array<boolean>) => void;
  goToNextStep: () => void;
  goToPreviousStep: () => void;
  isFirstStep: boolean;
  isLastStep: boolean;
  sendingRequest: boolean;
  step: Step;
}

interface ConfirmDialogLabels {
  labelCancel: string;
  labelConfirm: string;
  labelMessage: string;
  labelTitle: string;
}

export interface WizardProps {
  actionsBarLabels?: ActionsBarLabels;
  confirmDialogLabels?: ConfirmDialogLabels;
  fullHeight?: boolean;
  initialValues?: FormikValues;
  onClose?: () => void;
  onSubmit?: (values: FormikValues, bag: FormikHelpers<FormikValues>) => void;
  open: boolean;
  steps: Array<Step>;
  width?: 'lg' | 'md' | 'sm' | 'xl' | 'xs' | false;
}

export interface ActionsBarProps {
  actionsBarLabels: ActionsBarLabels;
  disableActionButtons: boolean;
  goToNextStep: () => void;
  goToPreviousStep: () => void;
  isFirstStep: boolean;
  isLastStep: boolean;
  isSubmitting: boolean;
  submit: () => void;
}
