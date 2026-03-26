import { FormHelperText } from '@mui/material';

interface Props {
  message: string;
  style: string;
}

const ErrorText = ({ message, style }: Props): JSX.Element => (
  <FormHelperText className={style} error>
    {message}
  </FormHelperText>
);

export default ErrorText;
