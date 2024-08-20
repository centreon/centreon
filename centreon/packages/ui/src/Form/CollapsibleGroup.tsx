import { useCallback, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import ExpandMore from '@mui/icons-material/ExpandMore';
import {
  Box,
  Collapse,
  ListItemButton,
  IconButton as MuiIconButton,
  Tooltip,
  Typography
} from '@mui/material';

import { Group } from './Inputs/models';

interface Props {
  children: React.ReactNode;
  className?: string;
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
  defaultIsOpen = false,
  className
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(isCollapsible && defaultIsOpen);

  const toggle = (): void => {
    setIsOpen((currentIsOpen) => !currentIsOpen);
  };

  const containerClassName = className || '';

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
          className={cx(classes.groupTitleContainer, containerClassName)}
          onClick={toggle}
        >
          {containerComponentChildren}
        </ListItemButton>
      ) : (
        <Box className={cx(classes.groupTitleContainer, containerClassName)}>
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
            <Typography
              className="groupText"
              variant="h5"
              {...group?.titleAttributes}
            >
              {t(group?.name as string)}
            </Typography>
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
