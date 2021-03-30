import * as React from 'react';

import { useTranslation } from 'react-i18next';
import { isNil } from 'ramda';

import {
  Button,
  ClickAwayListener,
  makeStyles,
  Paper,
  Popper,
  PopperPlacementType,
  useTheme,
} from '@material-ui/core';
import IconReset from '@material-ui/icons/RotateLeft';

import IconButton from '../../../../../Button/Icon';
import MultiAutocompleteField, {
  Props as MultiAutocompleteFieldProps,
} from '..';

import { labelReset } from './translatedLabels';

const useStyles = makeStyles((theme) => ({
  button: {
    fontSize: theme.typography.caption.fontSize,
  },
}));

type Props = MultiAutocompleteFieldProps & {
  icon: JSX.Element;
  title: string;
  onReset?: () => void;
  popperPlacement?: PopperPlacementType;
};

const IconPopoverMultiAutocomplete = ({
  icon,
  options,
  label,
  title,
  onChange,
  value,
  onReset,
  popperPlacement = 'bottom-start',
  ...props
}: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();
  const { t } = useTranslation();

  const [anchorEl, setAnchorEl] = React.useState();

  const isOpen = Boolean(anchorEl);

  const close = (reason?): void => {
    const isClosedByInputClick = reason?.type === 'mousedown';

    if (isClosedByInputClick) {
      return;
    }
    setAnchorEl(undefined);
  };

  const toggle = (event): void => {
    if (isOpen) {
      close();
      return;
    }

    setAnchorEl(event.currentTarget);
  };

  return (
    <ClickAwayListener onClickAway={close}>
      <div>
        <IconButton title={title} ariaLabel={title} onClick={toggle}>
          {icon}
        </IconButton>
        <Popper
          style={{ zIndex: theme.zIndex.tooltip }}
          open={isOpen}
          anchorEl={anchorEl}
          placement={popperPlacement}
        >
          <Paper>
            {!isNil(onReset) && (
              <Button
                className={classes.button}
                startIcon={<IconReset />}
                size="small"
                color="primary"
                fullWidth
                onClick={onReset}
              >
                {t(labelReset)}
              </Button>
            )}
            <MultiAutocompleteField
              onClose={close}
              label={label}
              options={options}
              onChange={onChange}
              value={value}
              open={isOpen}
              limitTags={1}
              {...props}
            />
          </Paper>
        </Popper>
      </div>
    </ClickAwayListener>
  );
};

export default IconPopoverMultiAutocomplete;
