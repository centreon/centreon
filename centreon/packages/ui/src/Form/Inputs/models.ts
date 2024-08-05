import { FormikValues } from 'formik';

import { SvgIconProps, TypographyProps } from '@mui/material';

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
  Custom,
  Checkbox,
  CheckboxGroup,
  List
}

interface FieldsTableGetRequiredProps {
  index: number;
  values: FormikValues;
}

export type LabelPlacement = 'bottom' | 'top' | 'end' | 'start' | undefined;

export interface InputProps {
  additionalLabel?: string | JSX.Element;
  additionalLabelClassName?: string;
  additionalMemoProps?: Array<unknown>;
  autoFocus?: boolean;
  autocomplete?: {
    creatable?: boolean;
    options: Array<SelectEntry>;
  };
  change?: ({ setFieldValue, value }) => void;
  checkbox?: {
    direction?: 'horizontal' | 'vertical';
    labelPlacement?: LabelPlacement;
    options?: Array<string>;
  };
  connectedAutocomplete?: {
    additionalConditionParameters: Array<ConditionsSearchParameter>;
    chipColor?: string;
    endpoint?: string;
    filterKey?: string;
    getRenderedOptionText?: (option) => string | JSX.Element;
  };
  custom?: {
    Component: React.ComponentType<InputPropsWithoutGroup>;
  };
  dataTestId?: string;
  disableSortedOptions?: boolean;
  fieldName: string;
  fieldsTable?: {
    additionalFieldsToMemoize?: Array<string>;
    columns: Array<Omit<InputProps, 'group'>>;
    defaultRowValue: object | string;
    deleteLabel: string;
    getRequired?: ({ values, index }: FieldsTableGetRequiredProps) => boolean;
    getSortable?: (values: FormikValues) => boolean;
    hasSingleValue?: boolean;
    sortableIdProperty?: string;
  };
  getDisabled?: (values: FormikValues) => boolean;
  getRequired?: (values: FormikValues) => boolean;
  grid?: {
    alignItems?: string;
    className?: string;
    columns: Array<Omit<InputProps, 'group'>>;
    gridTemplateColumns?: string;
  };
  group: string;
  hideInput?: (values: FormikValues) => boolean;
  inputClassName?: string;
  label: string;
  list?: {
    AddItem: React.ComponentType<{ addItem }>;
    SortContent: React.ComponentType<object>;
    addItemLabel?: string;
    itemProps: Array<string>;
    sortLabel?: string;
  };
  radio?: {
    options?: Array<{
      label: string | JSX.Element;
      value: boolean | string;
    }>;
    row?: boolean;
  };
  required?: boolean;
  switchInput?: {
    getChecked?: (value) => boolean;
  };
  text?: {
    endAdornment?: JSX.Element;
    multilineRows?: number;
    placeholder?: string;
    type?: string;
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
  titleAttributes?: TypographyProps;
}
