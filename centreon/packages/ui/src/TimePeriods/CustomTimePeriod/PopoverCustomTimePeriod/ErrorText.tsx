import { FormHelperText } from '@mui/material';

interface Props {
  message: string;
  style: string;
}

const ErrorText = ({ message, style }: Props): JSX.Element => (
  <FormHelperText error className={style}>
    {message}
  </FormHelperText>
);

export default ErrorText;
