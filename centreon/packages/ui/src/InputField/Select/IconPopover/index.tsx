import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { find, isNil, pipe, propEq, reject } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  Button,
  ClickAwayListener,
  MenuItem,
  Paper,
  Popper,
  PopperPlacementType,
  useTheme
} from '@mui/material';
import IconReset from '@mui/icons-material/RotateLeft';

import IconButton from '../../../Button/Icon';
import Option from '../Option';
import { SelectEntry } from '..';

import { labelReset } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  button: {
    fontSize: theme.typography.caption.fontSize
  }
}));

interface Props {
  icon: JSX.Element;
  onChange: (updatedValues: Array<SelectEntry>) => void;
  onReset?: () => void;
  options: Array<SelectEntry>;
  popperPlacement?: PopperPlacementType;
  title: string;
  value: Array<SelectEntry>;
}

const IconPopoverMultiAutocomplete = ({
  icon,
  options,
  title,
  onChange,
  value,
  onReset,
  popperPlacement = 'bottom-start'
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [anchorEl, setAnchorEl] = useState();

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

  const isSelected = (id: number | string): boolean => {
    return pipe(find(propEq('id', id)), Boolean)(value);
  };

  const unSelect = (option: SelectEntry): void => {
    const { id } = option;

    const updatedValue = isSelected(id)
      ? reject(propEq('id', id), value)
      : [...value, option];

    onChange(updatedValue);
  };

  return (
    <ClickAwayListener onClickAway={close}>
      <div>
        <IconButton
          ariaLabel={title}
          size="large"
          title={title}
          onClick={toggle}
        >
          {icon}
        </IconButton>
        <Popper
          anchorEl={anchorEl}
          open={isOpen}
          placement={popperPlacement}
          style={{ zIndex: theme.zIndex.tooltip }}
        >
          <Paper>
            {!isNil(onReset) && (
              <Button
                fullWidth
                className={classes.button}
                color="primary"
                size="small"
                startIcon={<IconReset />}
                onClick={onReset}
              >
                {t(labelReset)}
              </Button>
            )}
            {options.map((option) => {
              const { id, name } = option;

              return (
                <MenuItem
                  disabled={option.disabled || false}
                  key={id}
                  value={name}
                  onClick={(): void => unSelect(option)}
                >
                  <Option checkboxSelected={isSelected(id)}>{name}</Option>
                </MenuItem>
              );
            })}
          </Paper>
        </Popper>
      </div>
    </ClickAwayListener>
  );
};

export default IconPopoverMultiAutocomplete;
