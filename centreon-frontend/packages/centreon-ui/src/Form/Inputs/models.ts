import { FormikValues } from 'formik';

import { SvgIconProps } from '@mui/material';

import { SelectEntry } from '../../InputField/Select';

export enum InputType {
  Switch,
  Radio,
  Text,
  SingleAutocomplete,
  MultiAutocomplete,
  Password,
  SingleConnectedAutocomplete,
  MultiConnectedAutocomplete,
  FieldsTable,
  Grid,
  Custom,
}

interface FieldsTableGetRequiredProps {
  index: number;
  values: FormikValues;
}

export interface InputProps {
  additionalLabel?: string;
  additionalMemoProps?: Array<unknown>;
  autocomplete?: {
    creatable?: boolean;
    options: Array<SelectEntry>;
  };
  change?: ({ setFieldValue, value }) => void;
  connectedAutocomplete?: {
    endpoint?: string;
    filterKey?: string;
  };
  custom?: {
    Component: React.ComponentType<InputPropsWithoutGroup>;
  };
  fieldName: string;
  fieldsTable?: {
    additionalFieldsToMemoize?: Array<string>;
    columns: Array<Omit<InputProps, 'group'>>;
    defaultRowValue: object;
    deleteLabel: string;
    getRequired?: ({ values, index }: FieldsTableGetRequiredProps) => boolean;
  };
  getDisabled?: (values: FormikValues) => boolean;
  getRequired?: (values: FormikValues) => boolean;
  grid?: {
    columns: Array<Omit<InputProps, 'group'>>;
    gridTemplateColumns?: string;
  };
  group: string;
  label: string;
  radio?: {
    options?: Array<{
      label: string;
      value: boolean | string;
    }>;
  };
  required?: boolean;
  switchInput?: {
    getChecked?: (value) => boolean;
  };
  type: InputType;
}

export type InputPropsWithoutGroup = Omit<InputProps, 'group'>;

export interface Group {
  EndIcon?: (props: SvgIconProps) => JSX.Element;
  TooltipContent?: () => JSX.Element;
  name: string;
  order: number;
}
