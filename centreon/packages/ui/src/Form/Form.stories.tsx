import { Form, GroupDirection } from './Form';
import {
  type BasicForm,
  basicFormGroups,
  basicFormInitialValues,
  basicFormInputs,
  basicFormValidationSchema,
  CustomButton
} from './storiesData';

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
  <Form<BasicForm> {...mandatoryProps} />
);

export const basicFormWithGroups = (): JSX.Element => (
  <Form<BasicForm> {...mandatoryProps} groups={basicFormGroups} />
);

export const basicFormWithCollapsibleGroups = (): JSX.Element => (
  <Form<BasicForm> {...mandatoryProps} groups={basicFormGroups} isCollapsible />
);

export const basicFormWithCustomButton = (): JSX.Element => (
  <Form<BasicForm> {...mandatoryProps} Buttons={CustomButton} />
);

export const loadingForm = (): JSX.Element => (
  <Form<BasicForm> {...mandatoryProps} isLoading />
);

export const loadingFormWithGroups = (): JSX.Element => (
  <Form<BasicForm> {...mandatoryProps} groups={basicFormGroups} isLoading />
);

export const basicFormWithHorizontalGroups = (): JSX.Element => (
  <Form<BasicForm>
    {...mandatoryProps}
    groupDirection={GroupDirection.Horizontal}
    groups={basicFormGroups.filter((group) => group.order !== 3)}
  />
);
