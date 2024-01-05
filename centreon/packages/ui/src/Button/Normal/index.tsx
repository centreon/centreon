import {
  Typography,
  TypographyProps,
  Button as ButtonMui,
  ButtonProps
} from '@mui/material';

import { DataTestAttributes } from '../../@types/data-attributes';

interface Props {
  buttonProps?: ButtonProps;
  label: string;
  labelProps?: TypographyProps;
}

interface WrapperLabelProps {
  label: string;
}

const WrapperLabel = ({
  label,
  ...rest
}: WrapperLabelProps & TypographyProps): JSX.Element => {
  return (
    <Typography variant="button" {...rest}>
      {label}
    </Typography>
  );
};

const Button = ({
  label,
  buttonProps,
  labelProps,
  ...rest
}: Props & DataTestAttributes): JSX.Element => {
  return (
    <ButtonMui variant="contained" {...buttonProps} {...rest}>
      <WrapperLabel label={label} {...labelProps} />
    </ButtonMui>
  );
};

export default Button;
