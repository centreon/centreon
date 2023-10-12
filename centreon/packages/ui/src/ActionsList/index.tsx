/* eslint-disable react/no-array-index-key */
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

import { useStyles } from './ActionsList.styles';
import { ActionVariants } from './models';

interface ActionsType {
  Icon?: (props: SvgIconProps) => JSX.Element;
  label: string;
  onClick?: (e?) => void;
  secondaryLabel?: string;
  variant?: ActionVariants;
}

interface Props {
  actions: Array<ActionsType | 'divider'>;
  className?: string;
  listItemClassName?: string;
}

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

        const { label, Icon, onClick, variant, secondaryLabel } =
          action as ActionsType;

        return (
          <MenuItem
            aria-label={label}
            className={classes.item}
            data-variant={variant}
            id={label}
            key={label}
            onClick={onClick}
          >
            {Icon && (
              <ListItemIcon>
                <Icon fontSize="small" />
              </ListItemIcon>
            )}
            <ListItemText
              className={listItemClassName}
              primary={t(label)}
              secondary={secondaryLabel && t(secondaryLabel)}
            />
          </MenuItem>
        );
      })}
    </MenuList>
  );
};

export default ActionsList;
