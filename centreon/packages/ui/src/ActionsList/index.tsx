import * as React from 'react';

import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import {
  SvgIconProps,
  MenuList,
  MenuItem,
  ListItemText,
  ListItemIcon
} from '@mui/material';

interface ActionsType {
  Icon: (props: SvgIconProps) => JSX.Element;
  label: string;
  onClick?: () => void;
}

interface Props {
  actions: Array<ActionsType>;
  className?: string;
}

const useStyles = makeStyles()({
  list: {
    maxWidth: '100%'
  }
});

const ActionsList = ({ className, actions }: Props): JSX.Element => {
  const { cx, classes } = useStyles();
  const { t } = useTranslation();

  return (
    <MenuList className={cx(classes.list, className)}>
      {actions?.map(({ Icon, label, onClick }) => {
        return (
          <MenuItem aria-label={label} key={label} onClick={onClick}>
            <ListItemIcon>
              <Icon fontSize="small" />
            </ListItemIcon>
            <ListItemText>{t(label)}</ListItemText>
          </MenuItem>
        );
      })}
    </MenuList>
  );
};

export default ActionsList;
