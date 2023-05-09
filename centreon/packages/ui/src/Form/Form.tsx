import { Formik, FormikHelpers, FormikSharedConfig, FormikValues } from 'formik';
import * as Yup from 'yup';

import FormButtons from './FormButtons';
import Inputs from './Inputs';
import { Group, InputProps } from './Inputs/models';

import { useStyles } from './Form.styles';

export enum GroupDirection {
  Horizontal = 'horizontal',
  Vertical = 'vertical'
}

export type FormProps<T> = {
  Buttons?: React.ComponentType;
  groupDirection?: GroupDirection;
  groups?: Array<Group>;
  initialValues: T;
  inputs: Array<InputProps>;
  isCollapsible?: boolean;
  isLoading?: boolean;
  submit: (values: T, bag: FormikHelpers<T>) => void | Promise<void>;
  validate?: (values: FormikValues) => void;
  validationSchema: Yup.SchemaOf<T>;
} & Omit<FormikSharedConfig<T>, 'isInitialValid'>;

const Form = <T extends object>({
  initialValues,
  validate,
  validationSchema,
  submit,
  groups,
  inputs,
  Buttons = FormButtons,
  isLoading = false,
  isCollapsible = false,
  groupDirection = GroupDirection.Vertical,
  ...formikSharedConfig
}: FormProps<T>): JSX.Element => {
  const { classes } = useStyles();

  if (isLoading) {
    return (
      <Inputs
        isLoading
        groups={groups}
        inputs={inputs}
        isCollapsible={isCollapsible}
      />
    );
  }

  return (
    <Formik<T>
      initialValues={initialValues}
      validate={validate}
      validationSchema={validationSchema}
      onSubmit={submit}
      {...formikSharedConfig}
    >
      <div className={classes.form}>
        <Inputs
          groupDirection={groupDirection}
          groups={groups}
          inputs={inputs}
          isCollapsible={isCollapsible}
        />
        <Buttons />
      </div>
    </Formik>
  );
};

export { Form };
