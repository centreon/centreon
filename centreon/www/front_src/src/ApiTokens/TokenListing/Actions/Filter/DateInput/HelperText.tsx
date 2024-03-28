import { FormHelperText } from '@mui/material';

import { useStyles } from '../filter.styles';

interface Props {
  className?: string;
  error: string;
}

const HelperText = ({ error, className }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <div>
      {error && (
        <FormHelperText error className={cx(classes.helperText, className)}>
          {error}
        </FormHelperText>
      )}
    </div>
  );
};

export default HelperText;
