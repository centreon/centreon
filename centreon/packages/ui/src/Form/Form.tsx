import {
  Formik,
  FormikHelpers,
  FormikSharedConfig,
  FormikValues
} from 'formik';
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
  groupsAreOpen?: boolean;
  groupsClassName?: string;
  children?: JSX.Element;
  className?: string;
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
  children,
  className,
  groupsClassName,
  groupsAreOpen,
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
        groupsAreOpen={groupsAreOpen}
        groupsClassName={groupsClassName}
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
        {children}
        <div className={className}>
          <Inputs
            groupDirection={groupDirection}
            groups={groups}
            groupsAreOpen={groupsAreOpen}
            groupsClassName={groupsClassName}
            inputs={inputs}
            isCollapsible={isCollapsible}
          />
        </div>
        <Buttons />
      </div>
    </Formik>
  );
};

export { Form };
