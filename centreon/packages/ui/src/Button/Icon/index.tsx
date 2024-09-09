import { makeStyles } from 'tss-react/mui';

import {
  IconButtonProps,
  IconButton as MuiIconButton,
  Tooltip
} from '@mui/material';

import { getNormalizedId } from '../../utils';

const useStyles = makeStyles()((theme) => ({
  button: {
    padding: theme.spacing(0.25)
  },
  tooltip: {
    background: theme.palette.background.tooltip
  }
}));

type Props = {
  ariaLabel?: string;
  className?: string;
  onClick: (event) => void;
  title?: string | JSX.Element;
  tooltipClassName?: string;
  tooltipPlacement?:
    | 'bottom'
    | 'left'
    | 'right'
    | 'top'
    | 'bottom-end'
    | 'bottom-start'
    | 'left-end'
    | 'left-start'
    | 'right-end'
    | 'right-start'
    | 'top-end'
    | 'top-start';
} & IconButtonProps;

export const IconButton = ({
  title = '',
  ariaLabel,
  className,
  tooltipPlacement,
  tooltipClassName,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <Tooltip
      classes={{ tooltip: cx(classes.tooltip, tooltipClassName) }}
      placement={tooltipPlacement}
      title={title}
    >
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
