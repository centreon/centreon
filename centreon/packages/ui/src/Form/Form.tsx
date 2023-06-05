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
  Buttons?: React.ComponentType;
  children?: JSX.Element;
  className?: string;
  groupDirection?: GroupDirection;
  groups?: Array<Group>;
  areGoupsOpen?: boolean;
  groupsClassName?: string;
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
  areGoupsOpen,
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
  const { cx, classes } = useStyles();

  if (isLoading) {
    return (
      <Inputs
        isLoading
        groups={groups}
        areGoupsOpen={areGoupsOpen}
        groupsClassName={groupsClassName}
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
      <div>
        {children}
        <div className={cx(className, classes.form)}>
          <Inputs
            groupDirection={groupDirection}
            groups={groups}
            areGoupsOpen={areGoupsOpen}
            groupsClassName={groupsClassName}
            inputs={inputs}
            isCollapsible={isCollapsible}
          />
          <Buttons />
        </div>
      </div>
    </Formik>
  );
};

export { Form };
