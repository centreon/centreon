import { makeStyles } from 'tss-react/mui';

import {
  IconButton as MuiIconButton,
  IconButtonProps,
  Tooltip
} from '@mui/material';

import getNormalizedId from '../../utils/getNormalizedId';

const useStyles = makeStyles()((theme) => ({
  button: {
    padding: theme.spacing(0.25)
  }
}));

type Props = {
  ariaLabel?: string;
  className?: string;
  onClick: (event) => void;
  title?: string;
} & IconButtonProps;

const IconButton = ({
  title = '',
  ariaLabel,
  className,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <Tooltip title={title}>
      <span aria-label={undefined}>
        <MuiIconButton
          aria-label={ariaLabel}
          className={cx(classes.button, className)}
          color="primary"
          data-testid={ariaLabel}
          id={getNormalizedId(ariaLabel || '')}
          {...props}
        />
      </span>
    </Tooltip>
  );
};

export default IconButton;
