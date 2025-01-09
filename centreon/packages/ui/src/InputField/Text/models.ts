import {
  FormHelperTextProps,
  InputBaseProps,
  InputLabelProps,
  InputProps,
  SelectProps,
  SlotProps,
  TextFieldOwnerState
} from '@mui/material';

export interface SlotPropsTextField {
  input?: SlotProps<React.ElementType<InputProps>, {}, TextFieldOwnerState>;
  inputLabel?: SlotProps<
    React.ElementType<InputLabelProps>,
    {},
    TextFieldOwnerState
  >;
  htmlInput?: SlotProps<
    React.ElementType<InputBaseProps['inputProps']>,
    {},
    TextFieldOwnerState
  >;
  formHelperText?: SlotProps<
    React.ElementType<FormHelperTextProps>,
    {},
    TextFieldOwnerState
  >;
  select?: SlotProps<React.ElementType<SelectProps>, {}, TextFieldOwnerState>;
}
