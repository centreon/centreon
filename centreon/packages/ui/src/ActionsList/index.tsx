import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { SvgIconProps } from '@mui/material';
import MenuList from '@mui/material/MenuList';
import MenuItem from '@mui/material/MenuItem';
import ListItemText from '@mui/material/ListItemText';
import ListItemIcon from '@mui/material/ListItemIcon';

interface ActionsType {
  Icon: (props: SvgIconProps) => JSX.Element;
  label: string;
  onClick?: () => void;
}

interface Props {
  actions: Array<ActionsType>;
  className?: string;
}

const useStyles = makeStyles()(() => ({
  list: {
    maxWidth: '100%'
  }
}));

const ActionsList = ({ className, actions }: Props): JSX.Element => {
  const { cx, classes } = useStyles();

  return (
    <MenuList className={cx(classes.list, className)}>
      {actions?.map(({ Icon, label, onClick }) => {
        return (
          <MenuItem key={label} onClick={onClick}>
            <ListItemIcon>
              <Icon fontSize="small" />
            </ListItemIcon>
            <ListItemText>{label}</ListItemText>
          </MenuItem>
        );
      })}
    </MenuList>
  );
};

export default ActionsList;
