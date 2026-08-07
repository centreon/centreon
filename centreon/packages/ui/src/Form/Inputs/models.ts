import type { SvgIconProps, TypographyProps } from '@mui/material';

import type { FormikErrors, FormikTouched, FormikValues } from 'formik';
import type { JsonDecoder } from 'ts.data.json';

import type { ConditionsSearchParameter } from '../../api/buildListingEndpoint/models';
import type { SelectEntry } from '../../InputField/Select';
import type { QueryParameter } from '../../queryParameters/models';

export interface ChangeArgs {
  setFieldValue: (
    field: string,
    value: unknown,
    shouldValidate?: boolean
    // biome-ignore lint/suspicious/noConfusingVoidType: matches Formik's type signature
  ) => Promise<void | FormikErrors<FormikValues>>;
  setFieldTouched: (
    field: string,
    isTouched?: boolean,
    shouldValidate?: boolean
    // biome-ignore lint/suspicious/noConfusingVoidType: matches Formik's type signature
  ) => Promise<void | FormikErrors<FormikValues>>;
  setValues: (
    values: React.SetStateAction<FormikValues>,
    shouldValidate?: boolean
    // biome-ignore lint/suspicious/noConfusingVoidType: matches Formik's type signature
  ) => Promise<void | FormikErrors<FormikValues>>;
  setTouched: (
    touched: FormikTouched<FormikValues>,
    shouldValidate?: boolean
    // biome-ignore lint/suspicious/noConfusingVoidType: matches Formik's type signature
  ) => Promise<void | FormikErrors<FormikValues>>;
  value: unknown;
  values: FormikValues;
}

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
  File = 14,
  Divider = 15
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
  }: ChangeArgs) => void;
  checkbox?: {
    direction?: 'horizontal' | 'vertical';
    labelPlacement?: LabelPlacement;
    options?: Array<string>;
  };
  connectedAutocomplete?: {
    useNewAPIFormat?: boolean;
    additionalConditionParameters: Array<ConditionsSearchParameter>;
    customQueryParameters: Array<QueryParameter>;
    chipColor?: string;
    endpoint?: string;
    filterKey?: string;
    getRenderedOptionText?: (option: { name: string }) => string | JSX.Element;
    getOptionLabel?: (option: string | SelectEntry) => string;
    helperText?: string;
    optionProperty?: string;
    disableSelectAll?: boolean;
    limitTags?: number;
    decoder?: JsonDecoder.Decoder<unknown>;
  };
  file?: {
    multiple?: boolean;
    accept?: string;
    maxFileSize?: number;
    CustomDropZoneContent: ({
      files
    }: {
      files: FileList | null;
    }) => JSX.Element;
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
    AddItem: React.ComponentType<{
      addItem: (newItem: SelectEntry) => void;
    }>;
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
    getChecked?: (value: unknown) => boolean;
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
