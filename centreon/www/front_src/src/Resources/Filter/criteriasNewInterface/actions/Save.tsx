import { useState } from 'react';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { Popper } from '@mui/material';
import Button from '@mui/material/Button';
import ButtonGroup from '@mui/material/ButtonGroup';
import ClickAwayListener from '@mui/material/ClickAwayListener';
import MenuItem from '@mui/material/MenuItem';
import MenuList from '@mui/material/MenuList';
import Paper from '@mui/material/Paper';

import { PopoverData } from '../../Criterias/models';

import { useStyles } from './actions.style';

interface Option {
  disabled: boolean;
  onClick: () => void;
  title: string;
}

interface Save {
  canSaveFilter: boolean;
  getIsCreateFilter: (value: boolean) => void;
  getIsUpdateFilter: (value: boolean) => void;
  isNewFilter: boolean;
  popoverData: PopoverData | undefined;
}

const Save = ({
  isNewFilter,
  canSaveFilter,
  getIsCreateFilter,
  getIsUpdateFilter,
  popoverData
}: Save): JSX.Element => {
  const { classes } = useStyles();
  const [open, setOpen] = useState(false);
  const [selectedOption, setSelectedOption] = useState<Option | undefined>();
  const [anchorEl, setAnchorEl] = useState<null | HTMLDivElement>(null);
  const openPopper = Boolean(anchorEl);

  const handleToggle = (event): void => {
    setOpen(!open);
    setAnchorEl(anchorEl ? null : event.currentTarget);
  };

  const handleClose = (): void => {
    setOpen(false);
    setAnchorEl(null);
  };

  const saveAsNew = (): void => {
    getIsCreateFilter(true);
    popoverData?.setAnchorEl?.(undefined);
  };

  const saveAs = (): void => {
    getIsUpdateFilter(true);
    popoverData?.setAnchorEl?.(undefined);
  };

  const options = [
    {
      disabled: !isNewFilter,
      onClick: saveAsNew,
      title: 'Save as new'
    },
    {
      disabled: !canSaveFilter,
      onClick: saveAs,
      title: 'Save as'
    }
  ];

  const selectOption = (event, option: Option): void => {
    setSelectedOption(option);
    handleToggle(event);
  };
  const defaultCurrentOption =
    options.find(({ disabled }) => !disabled) ||
    options.find(({ title }) => title === 'Save as new');

  return (
    <ClickAwayListener onClickAway={handleClose}>
      <ButtonGroup aria-label="split button" color="primary" size="small">
        <div className={classes.container}>
          <div className={classes.subContainer}>
            <Button
              color="primary"
              disabled={
                selectedOption?.disabled ?? defaultCurrentOption?.disabled
              }
              size="small"
              onClick={selectedOption?.onClick || defaultCurrentOption?.onClick}
            >
              {selectedOption?.title || defaultCurrentOption?.title}
            </Button>
            <Button
              color="primary"
              disabled={options.every(({ disabled }) => disabled)}
              size="small"
              onClick={handleToggle}
            >
              <ArrowDropDownIcon />
            </Button>
          </div>
        </div>
        {open && (
          <Popper
            anchorEl={anchorEl}
            className={classes.popperButtonGroup}
            id="popperButtonGroup"
            open={openPopper}
            placement="bottom-end"
          >
            <Paper>
              <MenuList autoFocusItem id="split-button-menu">
                {options.map((option) => (
                  <MenuItem
                    disabled={option.disabled}
                    key={option.title}
                    onClick={(event) => selectOption(event, option)}
                  >
                    {option.title}
                  </MenuItem>
                ))}
              </MenuList>
            </Paper>
          </Popper>
        )}
      </ButtonGroup>
    </ClickAwayListener>
  );
};

export default Save;
