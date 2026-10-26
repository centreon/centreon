import { object } from 'yup';

import { Form, GroupDirection } from './Form';
import { InputType } from './Inputs/models';
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

export const exclusiveCheckboxGroup = (): JSX.Element => (
  <Form
    initialValues={{
      hostNotificationOptions: ['Down', 'Recovery'],
      serviceNotificationOptions: ['None']
    }}
    inputs={[
      {
        exclusiveCheckboxGroup: {
          direction: 'horizontal',
          exclusiveLabel: 'No notifications',
          exclusiveOption: 'None',
          options: [
            'Down',
            'Unreachable',
            'Recovery',
            'Flapping',
            'Downtime Scheduled',
            'None'
          ]
        },
        fieldName: 'hostNotificationOptions',
        group: '',
        label: 'Host notification options',
        type: InputType.ExclusiveCheckboxGroup
      },
      {
        exclusiveCheckboxGroup: {
          direction: 'horizontal',
          exclusiveLabel: 'No notifications',
          exclusiveOption: 'None',
          options: [
            'Warning',
            'Unknown',
            'Critical',
            'Recovery',
            'Flapping',
            'Downtime Scheduled',
            'None'
          ]
        },
        fieldName: 'serviceNotificationOptions',
        group: '',
        label: 'Service notification options',
        type: InputType.ExclusiveCheckboxGroup
      }
    ]}
    submit={submit}
    validationSchema={object()}
  />
);

export const segmentedButtons = (): JSX.Element => (
  <Form
    initialValues={{
      activeChecks: 'use_default',
      updateMode: 'incremental'
    }}
    inputs={[
      {
        fieldName: 'activeChecks',
        group: '',
        label: 'Active checks enabled',
        segmentedButtons: {
          options: [
            { label: 'Yes', value: 'true' },
            { label: 'No', value: 'false' },
            { label: 'Default', value: 'use_default' }
          ]
        },
        type: InputType.SegmentedButtons
      },
      {
        fieldName: 'updateMode',
        group: '',
        label: 'Update mode (long labels)',
        segmentedButtons: {
          options: [
            { label: 'Incremental', value: 'incremental' },
            { label: 'Replacement', value: 'replacement' },
            { label: 'Inherit from the host template', value: 'inherit' }
          ]
        },
        type: InputType.SegmentedButtons
      }
    ]}
    submit={submit}
    validationSchema={object()}
  />
);
