import { FormikValues } from 'formik';

import { SvgIconProps } from '@mui/material';

import { SelectEntry } from '../../InputField/Select';
import { ConditionsSearchParameter } from '../../api/buildListingEndpoint/models';

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
  Custom
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
    additionalConditionParameters: Array<ConditionsSearchParameter>;
    endpoint?: string;
    filterKey?: string;
  };
  custom?: {
    Component: React.ComponentType<InputPropsWithoutGroup>;
  };
  dataTestId?: string;
  fieldName: string;
  fieldsTable?: {
    additionalFieldsToMemoize?: Array<string>;
    columns: Array<Omit<InputProps, 'group'>>;
    defaultRowValue: object | string;
    deleteLabel: string;
    getRequired?: ({ values, index }: FieldsTableGetRequiredProps) => boolean;
    getSortable?: (values: FormikValues) => boolean;
    hasSingleValue?: boolean;
  };
  getDisabled?: (values: FormikValues) => boolean;
  getRequired?: (values: FormikValues) => boolean;
  grid?: {
    alignItems?: string;
    columns: Array<Omit<InputProps, 'group'>>;
    gridTemplateColumns?: string;
  };
  group: string;
  hideInput?: (values: FormikValues) => boolean;
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
  text?: {
    type: string;
  };
  type: InputType;
}

export type InputPropsWithoutGroup = Omit<InputProps, 'group'>;

export type InputPropsWithoutGroupAndType = Omit<InputProps, 'group' | 'type'>;

export interface Group {
  EndIcon?: (props: SvgIconProps) => JSX.Element;
  TooltipContent?: () => JSX.Element;
  name: string;
  order: number;
}
