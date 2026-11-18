import { object } from 'yup';

import { Form, GroupDirection } from './Form';
import { type InputProps, InputType } from './Inputs/models';
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

const getSeparatedFields = (count: number): Array<InputProps> =>
  Array.from({ length: count }, (_, index) => ({
    fieldName: `field${index + 1}`,
    group: '',
    label: `Field ${index + 1}`,
    type: InputType.Text
  }));

const getSeparatedGrid = ({
  className,
  count,
  name
}: {
  className: string;
  count: number;
  name: string;
}): InputProps => ({
  fieldName: name,
  grid: {
    className,
    columnSeparator: true,
    columns: getSeparatedFields(count).map(({ group, ...field }) => ({
      ...field,
      fieldName: `${name}-${field.fieldName}`
    }))
  },
  group: '',
  label: name,
  type: InputType.Grid
});

// The grids of this story show 1, 2 or 3 columns as the panel is resized (drag
// its bottom right corner): the lines only come between columns, on every row.
export const columnSeparators = (): JSX.Element => (
  <div className="@container resize-x overflow-auto border border-dashed p-4">
    <Form
      initialValues={{}}
      inputs={[
        getSeparatedGrid({
          className: 'grid-cols-1 gap-x-8 @[500px]:grid-cols-2',
          count: 2,
          name: 'two-columns'
        }),
        getSeparatedGrid({
          className:
            'grid-cols-1 gap-x-8 @[500px]:grid-cols-2 @[900px]:grid-cols-3',
          count: 3,
          name: 'three-columns'
        }),
        getSeparatedGrid({
          className:
            'grid-cols-1 gap-x-8 @[500px]:grid-cols-2 @[900px]:grid-cols-3',
          count: 5,
          name: 'wrapping-rows'
        })
      ]}
      submit={submit}
      validationSchema={object()}
    />
  </div>
);
