import { useCallback, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  Collapse,
  Tooltip,
  IconButton as MuiIconButton,
  Typography,
  ListItemButton,
  Box
} from '@mui/material';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import ExpandMore from '@mui/icons-material/ExpandMore';

import { Group } from './Inputs/models';

interface Props {
  children: React.ReactNode;
  defaultIsOpen?: boolean;
  group?: Group;
  hasGroupTitle: boolean;
  isCollapsible: boolean;
}

const useStyles = makeStyles()((theme) => ({
  expandCollapseIcon: {
    justifySelf: 'flex-end'
  },
  groupTitleContainer: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row'
  },
  groupTitleIcon: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row'
  },
  tooltip: {
    maxWidth: theme.spacing(60)
  }
}));

const CollapsibleGroup = ({
  children,
  isCollapsible,
  group,
  hasGroupTitle,
  defaultIsOpen = false
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(isCollapsible && defaultIsOpen);

  const toggle = (): void => {
    setIsOpen((currentIsOpen) => !currentIsOpen);
  };

  const CollapseIcon = isOpen ? ExpandMore : ChevronRightIcon;
  const ContainerComponent = useCallback(
    ({
      children: containerComponentChildren
    }: Pick<Props, 'children'>): JSX.Element =>
      isCollapsible ? (
        <ListItemButton
          dense
          disableGutters
          disableRipple
          aria-label={group?.name}
          className={classes.groupTitleContainer}
          onClick={toggle}
        >
          {containerComponentChildren}
        </ListItemButton>
      ) : (
        <Box className={classes.groupTitleContainer}>
          {containerComponentChildren}
        </Box>
      ),
    [isCollapsible]
  );

  return (
    <>
      {hasGroupTitle && (
        <ContainerComponent>
          {isCollapsible && <CollapseIcon />}
          <div className={classes.groupTitleIcon}>
            <Typography variant="h5">{t(group?.name as string)}</Typography>
            {group?.EndIcon && (
              <Tooltip
                classes={{
                  tooltip: classes.tooltip
                }}
                placement="top"
                title={group?.TooltipContent ? <group.TooltipContent /> : ''}
              >
                <MuiIconButton size="small">
                  <group.EndIcon fontSize="small" />
                </MuiIconButton>
              </Tooltip>
            )}
          </div>
        </ContainerComponent>
      )}
      {isCollapsible ? <Collapse in={isOpen}>{children}</Collapse> : children}
    </>
  );
};

export default CollapsibleGroup;
