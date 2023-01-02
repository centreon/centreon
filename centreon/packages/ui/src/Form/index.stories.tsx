import { Paper } from '@mui/material';

import {
  BasicForm,
  basicFormGroups,
  basicFormInitialValues,
  basicFormInputs,
  basicFormValidationSchema,
  CustomButton
} from './storiesData';

import Form, { GroupDirection } from '.';

export default { title: 'Form' };

const submit = (_, { setSubmitting }): void => {
  setSubmitting(true);
  setTimeout(() => {
    setSubmitting(false);
  }, 700);
};

const mandatoryProps = {
  initialValues: basicFormInitialValues,
  inputs: basicFormInputs,
  submit,
  validationSchema: basicFormValidationSchema
};

export const basicForm = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm> {...mandatoryProps} />
  </Paper>
);

export const basicFormWithGroups = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm> {...mandatoryProps} groups={basicFormGroups} />
  </Paper>
);

export const basicFormWithCollapsibleGroups = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm>
      {...mandatoryProps}
      isCollapsible
      groups={basicFormGroups}
    />
  </Paper>
);

export const basicFormWithCustomButton = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm> {...mandatoryProps} Buttons={CustomButton} />
  </Paper>
);

export const loadingForm = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm> {...mandatoryProps} isLoading />
  </Paper>
);

export const loadingFormWithGroups = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm> {...mandatoryProps} isLoading groups={basicFormGroups} />
  </Paper>
);

export const basicFormWithHorizontalGroups = (): JSX.Element => (
  <Paper elevation={0} sx={{ p: 1 }}>
    <Form<BasicForm>
      {...mandatoryProps}
      groupDirection={GroupDirection.Horizontal}
      groups={basicFormGroups}
    />
  </Paper>
);
