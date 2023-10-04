import { useRef, useState, useEffect } from 'react';

import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { Typography } from '@mui/material';
import Button from '@mui/material/Button';
import ButtonGroup from '@mui/material/ButtonGroup';
import ClickAwayListener from '@mui/material/ClickAwayListener';
import MenuItem from '@mui/material/MenuItem';
import MenuList from '@mui/material/MenuList';
import Paper from '@mui/material/Paper';

import { useSnackbar } from '@centreon/ui';

import { labelFilterCreated } from '../../../translatedLabels';
import CreateFilterDialog from '../../Save/CreateFilterDialog';
import useActionFilter from '../../Save/useActionFilter';
import { Filter } from '../../models';
import { currentFilterAtom } from '../../filterAtoms';

const Save = ({}): JSX.Element => {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [selectedOption, setSelectedOption] = useState();
  const [isCreateFilterDialogOpen, setIsCreateFilterDialogOpen] =
    useState(false);
  const currentFilter = useAtomValue(currentFilterAtom);

  const { showSuccessMessage } = useSnackbar();

  const {
    canSaveFilter,
    canSaveFilterAsNew,
    loadFiltersAndUpdateCurrent,
    sendingListCustomFiltersRequest,
    updateFilter,
    sendingUpdateFilterRequest
  } = useActionFilter({
    callbackSuccessUpdateFilter: () => alert('duplicate')
  });

  const handleToggle = (): void => {
    setOpen((prevOpen) => !prevOpen);
  };

  const handleClose = (): void => {
    setOpen(false);
  };
  const closeCreateFilterDialog = () => {
    setIsCreateFilterDialogOpen(false);
  };

  const confirmCreateFilter = (newFilter: Filter): void => {
    showSuccessMessage(t(labelFilterCreated));

    loadFiltersAndUpdateCurrent(omit(['order'], newFilter));

    closeCreateFilterDialog();
  };

  const saveAsNew = (selectedOption): void => {
    setIsCreateFilterDialogOpen(true);
  };
  const saveAs = (selectedOption): void => {};
  const options = [
    {
      disabled: !canSaveFilterAsNew,
      onClick: (data: string) => saveAsNew(data),
      title: 'Save as new'
    },
    {
      disabled: !canSaveFilter,
      onClick: (data: string) => saveAs(data),
      title: 'Save as'
    }
  ];

  const currentOption = options.find(({ title }) => title === selectedOption);

  return (
    <>
      <ClickAwayListener onClickAway={handleClose}>
        <ButtonGroup
          aria-label="split button"
          style={{ backgroundColor: '#255891', maxHeight: 36 }}
          variant="contained"
        >
          <div style={{ display: 'flex', flexDirection: 'column' }}>
            <div
              style={{ display: 'flex', flexDirection: 'row', minWidth: 99 }}
            >
              <Typography style={{ flex: 1 }}>
                {currentOption?.title || 'test'}
              </Typography>
              <Button
                aria-label="select merge strategy"
                size="small"
                onClick={handleToggle}
              >
                <ArrowDropDownIcon />
              </Button>
            </div>

            {open && (
              <Paper>
                <MenuList autoFocusItem id="split-button-menu">
                  {options.map(({ title, onClick, disabled }, index) => (
                    <MenuItem
                      disabled={disabled}
                      key={title}
                      onClick={(): void => onClick(title)}
                    >
                      {title}
                    </MenuItem>
                  ))}
                </MenuList>
              </Paper>
            )}
          </div>
        </ButtonGroup>
      </ClickAwayListener>

      {/* {isCreateFilterDialogOpen && (
        <CreateFilterDialog
          filter={currentFilter}
          open={isCreateFilterDialogOpen}
          onCancel={() => {
            setIsCreateFilterDialogOpen(false);
          }}
          onCreate={confirmCreateFilter}
        />
      )} */}
    </>
  );
};

export default Save;
