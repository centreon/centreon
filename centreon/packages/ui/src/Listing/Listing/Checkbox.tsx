import { makeStyles } from 'tss-react/mui';

import { Checkbox as MuiCheckbox, CheckboxProps } from '@mui/material';

const useStyles = makeStyles()({
  container: {
    padding: 0
  }
});

const Checkbox = ({
  className,
  ...props
}: { className?: string } & Omit<
  CheckboxProps,
  'size' | 'color'
>): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <MuiCheckbox
      className={cx(classes.container, className)}
      color="primary"
      size="small"
      {...props}
    />
  );
};

export default Checkbox;
