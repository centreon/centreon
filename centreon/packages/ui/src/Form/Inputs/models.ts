import { FormikValues } from 'formik';

import { SvgIconProps, TypographyProps } from '@mui/material';

import { SelectEntry } from '../../InputField/Select';
import { ConditionsSearchParameter } from '../../api/buildListingEndpoint/models';
import { QueryParameter } from '../../queryParameters/models';

export enum InputType {
  Switch = 0,
  Radio = 1,
  Text = 2,
  SingleAutocomplete = 3,
  MultiAutocomplete = 4,
  Password = 5,
  SingleConnectedAutocomplete = 6,
  MultiConnectedAutocomplete = 7,
  FieldsTable = 8,
  Grid = 9,
  Custom = 10,
  Checkbox = 11,
  CheckboxGroup = 12,
  List = 13,
  File = 14
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
    fullWidth?: boolean;
  };
  change?: ({
    setFieldValue,
    value,
    setFieldTouched,
    setValues,
    values,
    setTouched
  }) => void;
  checkbox?: {
    direction?: 'horizontal' | 'vertical';
    labelPlacement?: LabelPlacement;
    options?: Array<string>;
  };
  connectedAutocomplete?: {
    additionalConditionParameters: Array<ConditionsSearchParameter>;
    customQueryParameters: Array<QueryParameter>;
    chipColor?: string;
    endpoint?: string;
    filterKey?: string;
    getRenderedOptionText?: (option) => string | JSX.Element;
    disableSelectAll?: boolean;
    limitTags?: number;
    decoder?;
  };
  file?: {
    multiple?: boolean;
    accept?: string;
    maxFileSize?: number;
    CustomDropZoneContent: ({ files }) => JSX.Element;
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
    min?: number;
    fullWidth?: boolean;
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
  isDividerHidden?: boolean;
}
