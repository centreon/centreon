import { useState } from 'react';

import { equals, isEmpty, omit, propEq, reject } from 'ramda';
import { useAtomValue } from 'jotai';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { Popper } from '@mui/material';
import Button from '@mui/material/Button';
import ButtonGroup from '@mui/material/ButtonGroup';
import ClickAwayListener from '@mui/material/ClickAwayListener';
import MenuItem from '@mui/material/MenuItem';
import MenuList from '@mui/material/MenuList';
import Paper from '@mui/material/Paper';

import { labelSave, labelSaveAsNew } from '../../../translatedLabels';
import { currentFilterAtom, customFiltersAtom } from '../../filterAtoms';
import {
  allFilter,
  resourceProblemsFilter,
  unhandledProblemsFilter
} from '../../models';
import { Criteria } from '../../Criterias/models';

import { useStyles } from './actions.style';

interface Option {
  disabled: boolean;
  onClick: () => void;
  title: string;
}

interface Save {
  canSaveFilter: boolean;
  canSaveFilterAsNew: boolean;
  closePopover?: () => void;
  getIsCreateFilter: (value: boolean) => void;
  getIsUpdateFilter: (value: boolean) => void;
}

const getSelectableCriterias = (
  criterias: Array<Criteria>
): Array<Criteria> => {
  const filteredCriterias = reject<Criteria>(propEq('name', 'sort'))(criterias);

  return filteredCriterias.map(omit(['search_data']));
};

const Save = ({
  canSaveFilterAsNew,
  canSaveFilter,
  getIsCreateFilter,
  getIsUpdateFilter,
  closePopover
}: Save): JSX.Element => {
  const { classes } = useStyles();
  const [open, setOpen] = useState(false);
  const [selectedOption, setSelectedOption] = useState<Option | undefined>();
  const [anchorEl, setAnchorEl] = useState<null | HTMLDivElement>(null);
  const openPopper = Boolean(anchorEl);

  const currentFilter = useAtomValue(currentFilterAtom);
  const customFilters = useAtomValue(customFiltersAtom);

  const baseFilters = [
    unhandledProblemsFilter,
    resourceProblemsFilter,
    allFilter
  ];

  const selectableFilters = [...baseFilters, ...customFilters];

  const isNewFilter = isEmpty(currentFilter.id);

  const selectedCustomFilter = isNewFilter
    ? null
    : selectableFilters.find(propEq('id', currentFilter.id));

  const saveButtonDisabled =
    !isNewFilter &&
    equals(
      getSelectableCriterias(currentFilter.criterias),
      getSelectableCriterias(selectedCustomFilter?.criterias || [])
    );

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
    closePopover?.();
  };

  const saveAs = (): void => {
    getIsUpdateFilter(true);
    closePopover?.();
  };

  const options = [
    {
      disabled: !canSaveFilterAsNew,
      onClick: saveAsNew,
      title: labelSaveAsNew
    },
    {
      disabled: !canSaveFilter,
      onClick: saveAs,
      title: labelSave
    }
  ];

  const selectOption = (event, option: Option): void => {
    setSelectedOption(option);
    handleToggle(event);
  };
  const defaultCurrentOption =
    options.find(({ disabled }) => !disabled) ||
    options.find(({ title }) => title === labelSaveAsNew);

  return (
    <ClickAwayListener onClickAway={handleClose}>
      <ButtonGroup
        aria-label="split button"
        color="primary"
        disabled={saveButtonDisabled}
        size="small"
      >
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
