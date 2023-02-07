import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { SvgIconProps } from '@mui/material';
import Paper from '@mui/material/Paper';
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

const useStyles = makeStyles()((theme) => ({
  list: { maxWidth: '100%', width: theme.spacing(25) }
}));

const List = ({ className, actions }: Props): JSX.Element => {
  const { cx, classes } = useStyles();

  return (
    <Paper className={cx(classes.list, className)}>
      <MenuList>
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
    </Paper>
  );
};

export default List;
