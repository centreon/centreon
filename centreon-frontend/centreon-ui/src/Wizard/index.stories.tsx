import * as React from 'react';

import { useFormikContext, FormikErrors, FormikValues } from 'formik';

import { Typography, TextField } from '@material-ui/core';

import { StepComponentProps } from './models';

import Wizard from '.';

export default { title: 'Wizard' };

export const oneStep = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
    ]}
  />
);

export const oneStepWithoutActionsBar = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        hasActionsBar: false,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
    ]}
  />
);

export const threeStepsWithMediumSize = (): JSX.Element => (
  <Wizard
    open
    width="md"
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
      {
        stepName: 'Second step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 2
          </Typography>
        ),
      },
      {
        stepName: 'Third step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 3
          </Typography>
        ),
      },
    ]}
  />
);

export const threeStepsWithCustomActionsBarLabels = (): JSX.Element => (
  <Wizard
    open
    width="md"
    actionsBarLabels={{
      labelPrevious: 'Previous step',
      labelNext: 'Next step',
      labelFinish: 'Finish wizard',
    }}
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
      {
        stepName: 'Second step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 2
          </Typography>
        ),
      },
      {
        stepName: 'Third step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 3
          </Typography>
        ),
      },
    ]}
  />
);

export const threeStepsWithFullHeight = (): JSX.Element => (
  <Wizard
    open
    width="md"
    fullHeight
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
      {
        stepName: 'Second step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 2
          </Typography>
        ),
      },
      {
        stepName: 'Third step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 3
          </Typography>
        ),
      },
    ]}
  />
);

export const threeStepsWithCustomConfirmDialogLabels = (): JSX.Element => (
  <Wizard
    open
    width="md"
    confirmDialogLabels={{
      labelTitle: 'Exit wizard ?',
      labelMessage: "Wizard's progress will not be saved",
      labelConfirm: 'Exit',
      labelCancel: 'Cancel',
    }}
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 1
          </Typography>
        ),
      },
      {
        stepName: 'Second step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 2
          </Typography>
        ),
      },
      {
        stepName: 'Third step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 3
          </Typography>
        ),
      },
    ]}
  />
);

const FirstStep = ({ disableNextOnSendingRequests }: StepComponentProps) => {
  React.useEffect(() => {
    disableNextOnSendingRequests([true, false, true]);
    setTimeout(() => {
      disableNextOnSendingRequests([false, false, false]);
    }, 1500);
  }, []);

  return (
    <Typography variant="h5" align="center">
      Sending request...
    </Typography>
  );
};

export const twoStepsWithSendingRequest = (): JSX.Element => (
  <Wizard
    open
    steps={[
      {
        stepName: 'First step',
        skipFormChangeCheck: true,
        Component: FirstStep,
      },
      {
        stepName: 'Second step',
        skipFormChangeCheck: true,
        Component: (): JSX.Element => (
          <Typography variant="h5" align="center">
            Step 2
          </Typography>
        ),
      },
    ]}
  />
);

interface Values {
  email?: string;
  password?: string;
}

const Form = (): JSX.Element => {
  const [submitted, setSubmitted] = React.useState<boolean>(false);
  return (
    <Wizard
      initialValues={{
        email: '',
        password: '',
      }}
      onSubmit={(_, { setSubmitting }) => {
        setTimeout(() => {
          setSubmitting(false);
          setSubmitted(true);
        }, 500);
      }}
      open
      steps={[
        {
          stepName: 'First Step',
          validate: (values: Values) => {
            const errors: FormikErrors<FormikValues> = {};
            if (!values.email) {
              errors.email = 'Required';
            } else if (
              !/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(values.email)
            ) {
              errors.email = 'Invalid email address';
            }
            return errors;
          },
          Component: () => {
            const {
              handleChange,
              handleBlur,
              values,
              errors,
              touched,
            } = useFormikContext<Values>();

            return (
              <TextField
                type="email"
                name="email"
                label="email"
                onChange={handleChange('email')}
                onBlur={handleBlur('email')}
                value={values.email}
                error={!!touched.email && !!errors.email}
                helperText={touched.email ? errors.email : ''}
              />
            );
          },
        },
        {
          stepName: 'Second Step',
          validate: (values: Values) => {
            const errors: FormikErrors<FormikValues> = {};

            if (!values.password) {
              errors.password = 'Required';
            } else if (values.password.length < 6) {
              errors.password = 'Password too short';
            }

            return errors;
          },
          Component: ({ disableNextOnSendingRequests }: StepComponentProps) => {
            const {
              setFieldValue,
              values,
              errors,
              touched,
              handleChange,
              handleBlur,
            } = useFormikContext<Values>();
            React.useEffect(() => {
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
                type="password"
                name="password"
                label="password"
                onChange={handleChange('password')}
                onBlur={handleBlur('password')}
                value={values.password}
                error={!!touched.password && !!errors.password}
                helperText={touched.password ? errors.password : ''}
              />
            );
          },
        },
        {
          stepName: 'Third Step',
          Component: () => {
            const { values } = useFormikContext();
            return (
              <Typography>
                {!submitted ? JSON.stringify(values) : 'Values submitted'}
              </Typography>
            );
          },
        },
      ]}
    />
  );
};

export const wizardWithInputsAndValidation = (): JSX.Element => <Form />;
