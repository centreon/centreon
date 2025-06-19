import { useCallback, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { ExpandLess, ExpandMore } from '@mui/icons-material';

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
  },
  title: {}
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

  const CollapseIcon = isOpen ? ExpandLess : ExpandMore;
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
          className={`${cx(classes.groupTitleContainer, containerClassName)} bg-background-listing-header`}
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
          <div
            className={`${classes.groupTitleIcon} snap-y flex flex-row justify-between w-full pl-3 pr-1 text-white`}
          >
            <Typography
              className="groupText scroll-m-12 snap-start"
              variant="h6"
              {...group?.titleAttributes}
              data-section-group-form-id={group?.name}
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
            {isCollapsible && <CollapseIcon />}
          </div>
        </ContainerComponent>
      )}
      {isCollapsible ? <Collapse in={isOpen}>{children}</Collapse> : children}
    </>
  );
};

export default CollapsibleGroup;
