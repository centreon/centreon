import { useEffect, useState } from 'react';

import { useFormikContext, FormikErrors, FormikValues } from 'formik';

import { Typography, TextField } from '@mui/material';

import { StepComponentProps } from './models';

import Wizard from '.';

export default { title: 'Wizard' };

export const oneStep = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'First step'
      }
    ]}
  />
);

export const oneStepWithoutActionsBar = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        hasActionsBar: false,
        skipFormChangeCheck: true,
        stepName: 'First step'
      }
    ]}
  />
);

export const threeStepsWithMediumSize = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'First step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 2
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Second step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 3
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Third step'
      }
    ]}
    width="md"
  />
);

export const threeStepsWithCustomActionsBarLabels = (): JSX.Element => (
  <Wizard
    open
    actionsBarLabels={{
      labelFinish: 'Finish wizard',
      labelNext: 'Next step',
      labelPrevious: 'Previous step'
    }}
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'First step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 2
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Second step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 3
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Third step'
      }
    ]}
    width="md"
  />
);

export const threeStepsWithFullHeight = (): JSX.Element => (
  <Wizard
    fullHeight
    open
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'First step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 2
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Second step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 3
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Third step'
      }
    ]}
    width="md"
  />
);

export const threeStepsWithCustomConfirmDialogLabels = (): JSX.Element => (
  <Wizard
    open
    confirmDialogLabels={{
      labelCancel: 'Cancel',
      labelConfirm: 'Exit',
      labelMessage: "Wizard's progress will not be saved",
      labelTitle: 'Exit wizard ?'
    }}
    steps={[
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 1
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'First step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 2
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Second step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 3
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Third step'
      }
    ]}
    width="md"
  />
);

const FirstStep = ({
  disableNextOnSendingRequests
}: StepComponentProps): JSX.Element => {
  useEffect(() => {
    disableNextOnSendingRequests([true, false, true]);
    setTimeout(() => {
      disableNextOnSendingRequests([false, false, false]);
    }, 1500);
  }, []);

  return (
    <Typography align="center" variant="h5">
      Sending request...
    </Typography>
  );
};

export const twoStepsWithSendingRequest = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        Component: FirstStep,
        skipFormChangeCheck: true,
        stepName: 'First step'
      },
      {
        Component: (): JSX.Element => (
          <Typography align="center" variant="h5">
            Step 2
          </Typography>
        ),
        skipFormChangeCheck: true,
        stepName: 'Second step'
      }
    ]}
  />
);

interface Values {
  email?: string;
  password?: string;
}

const FirstStepWithTextField = (): JSX.Element => {
  const { handleChange, handleBlur, values, errors, touched } =
    useFormikContext<Values>();

  return (
    <TextField
      error={!!touched.email && !!errors.email}
      helperText={touched.email ? errors.email : ''}
      label="email"
      name="email"
      type="email"
      value={values.email}
      onBlur={handleBlur('email')}
      onChange={handleChange('email')}
    />
  );
};

const SecondStep = ({
  disableNextOnSendingRequests
}: StepComponentProps): JSX.Element => {
  const { setFieldValue, values, errors, touched, handleChange, handleBlur } =
    useFormikContext<Values>();
  useEffect(() => {
    if (!values.password) {
      disableNextOnSendingRequests([true, false, true]);
      setTimeout(() => {
        disableNextOnSendingRequests([false, false, false]);
        setFieldValue('password', 'pwd');
      }, 1000);
    }
  }, []);

  return (
    <TextField
      error={!!touched.password && !!errors.password}
      helperText={touched.password ? errors.password : ''}
      label="password"
      name="password"
      type="password"
      value={values.password}
      onBlur={handleBlur('password')}
      onChange={handleChange('password')}
    />
  );
};

const ThirdStep = (submitted: boolean): (() => JSX.Element) => {
  const Step = (): JSX.Element => {
    const { values } = useFormikContext();

    return (
      <Typography>
        {!submitted ? JSON.stringify(values) : 'Values submitted'}
      </Typography>
    );
  };

  return Step;
};

const Form = (): JSX.Element => {
  const [submitted, setSubmitted] = useState<boolean>(false);

  return (
    <Wizard
      open
      initialValues={{
        email: '',
        password: ''
      }}
      steps={[
        {
          Component: FirstStepWithTextField,
          stepName: 'First Step',
          validate: (values: Values): FormikErrors<FormikValues> => {
            const errors: FormikErrors<FormikValues> = {};
            if (!values.email) {
              errors.email = 'Required';
            }

            return errors;
          }
        },
        {
          Component: SecondStep,
          stepName: 'Second Step',
          validate: (values: Values): FormikErrors<FormikValues> => {
            const errors: FormikErrors<FormikValues> = {};

            if (!values.password) {
              errors.password = 'Required';
            } else if (values.password.length < 6) {
              errors.password = 'Password too short';
            }

            return errors;
          }
        },
        {
          Component: ThirdStep(submitted),
          stepName: 'Third Step'
        }
      ]}
      onSubmit={(_, { setSubmitting }): void => {
        setTimeout(() => {
          setSubmitting(false);
          setSubmitted(true);
        }, 500);
      }}
    />
  );
};

export const wizardWithInputsAndValidation = (): JSX.Element => <Form />;
