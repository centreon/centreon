/* eslint-disable react/no-array-index-key */
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import {
  SvgIconProps,
  MenuList,
  MenuItem,
  ListItemText,
  ListItemIcon,
  Divider
} from '@mui/material';

interface ActionsType {
  Icon?: (props: SvgIconProps) => JSX.Element;
  label: string;
  onClick?: (e?) => void;
}

interface Props {
  actions: Array<ActionsType | 'divider'>;
  className?: string;
  listItemClassName?: string;
}

const useStyles = makeStyles()({
  list: {
    maxWidth: '100%'
  }
});

const ActionsList = ({
  className,
  listItemClassName,
  actions
}: Props): JSX.Element => {
  const { cx, classes } = useStyles();
  const { t } = useTranslation();

  return (
    <MenuList className={cx(classes.list, className)}>
      {actions?.map((action, idx) => {
        if (equals(action, 'divider')) {
          return <Divider key={`divider_${idx}`} />;
        }

        const { label, Icon, onClick } = action as ActionsType;

        return (
          <MenuItem aria-label={label} id={label} key={label} onClick={onClick}>
            {Icon && (
              <ListItemIcon>
                <Icon fontSize="small" />
              </ListItemIcon>
            )}
            <ListItemText className={listItemClassName}>
              {t(label)}
            </ListItemText>
          </MenuItem>
        );
      })}
    </MenuList>
  );
};

export default ActionsList;
