import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Divider,
  ListItemIcon,
  ListItemText,
  MenuItem,
  MenuList,
  SvgIconTypeMap
} from '@mui/material';
import { OverridableComponent } from '@mui/material/OverridableComponent';

import { sanitizedHTML } from '..';

import { useStyles } from './ActionsList.styles';
import { ActionVariants } from './models';

interface ActionsType {
  Icon?: OverridableComponent<SvgIconTypeMap<object, 'svg'>> & {
    muiName: string;
  };
  label: string;
  onClick?: (e?) => void;
  secondaryLabel?: string;
  variant?: ActionVariants;
}

export enum ActionsListActionDivider {
  divider = 'divider'
}
export type ActionsListActions = Array<ActionsType | ActionsListActionDivider>;

interface Props {
  actions: ActionsListActions;
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
        if (equals(action, ActionsListActionDivider.divider)) {
          // biome-ignore lint/suspicious/noArrayIndexKey:
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
              secondary={
                secondaryLabel &&
                sanitizedHTML({ initialContent: t(secondaryLabel) })
              }
            />
          </MenuItem>
        );
      })}
    </MenuList>
  );
};

export default ActionsList;
