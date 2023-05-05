import { FormHelperText } from '@mui/material';

interface Props {
  message: string;
}

const ErrorText = ({ message }: Props): JSX.Element => (
  <FormHelperText error>{message}</FormHelperText>
);

export default ErrorText;
