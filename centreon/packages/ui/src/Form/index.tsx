import { Formik, FormikHelpers, FormikValues } from 'formik';
import * as Yup from 'yup';

import FormButtons from './FormButtons';
import Inputs from './Inputs';
import { Group, InputProps } from './Inputs/models';

export enum GroupDirection {
  Horizontal = 'horizontal',
  Vertical = 'vertical'
}

interface Props<T> {
  Buttons?: React.ComponentType;
  children?: JSX.Element;
  className?: string;
  groupDirection?: GroupDirection;
  groups?: Array<Group>;
  groupsClassName?: string;
  initialValues: T;
  inputs: Array<InputProps>;
  isCollapsible?: boolean;
  isLoading?: boolean;
  submit: (values: T, bag: FormikHelpers<T>) => void | Promise<void>;
  validate?: (values: FormikValues) => void;
  validationSchema: Yup.SchemaOf<T>;
}

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
  children,
  className,
  groupsClassName
}: Props<T>): JSX.Element => {
  if (isLoading) {
    return (
      <Inputs
        groupsAreOpen
        isLoading
        groups={groups}
        groupsClassName={groupsClassName}
        inputs={inputs}
        isCollapsible={isCollapsible}
      />
    );
  }

  return (
    <Formik<T>
      enableReinitialize
      validateOnBlur
      validateOnMount
      initialValues={initialValues}
      validate={validate}
      validationSchema={validationSchema}
      onSubmit={submit}
    >
      <div>
        {children}
        <div className={className}>
          <Inputs
            groupsAreOpen
            groupDirection={groupDirection}
            groups={groups}
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

export default Form;
