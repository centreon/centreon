import { ExpandLess, ExpandMore } from '@mui/icons-material';
import {
  Box,
  Collapse,
  ListItemButton,
  IconButton as MuiIconButton,
  Tooltip,
  Typography
} from '@mui/material';

import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { Group } from './Inputs/models';

interface Props {
  children: React.ReactNode;
  className?: string;
  defaultIsOpen?: boolean;
  group?: Group;
  hasGroupTitle: boolean;
  isCollapsible: boolean;
}

const CollapsibleGroup = ({
  children,
  isCollapsible,
  group,
  hasGroupTitle,
  defaultIsOpen = false,
  className
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(isCollapsible && defaultIsOpen);

  const toggle = (): void => {
    setIsOpen((currentIsOpen) => !currentIsOpen);
  };

  const containerClassName = `flex items-center gap-x-2 ${className || ''}`;

  const CollapseIcon = isOpen ? ExpandLess : ExpandMore;
  const ContainerComponent = useCallback(
    ({
      children: containerComponentChildren
    }: Pick<Props, 'children'>): JSX.Element =>
      isCollapsible ? (
        <ListItemButton
          aria-label={group?.name}
          className={`${containerClassName} bg-background-listing-header rounded-lg`}
          dense
          disableGutters
          disableRipple
          onClick={toggle}
        >
          {containerComponentChildren}
        </ListItemButton>
      ) : (
        <Box className={containerClassName}>{containerComponentChildren}</Box>
      ),
    [isCollapsible, containerClassName, group?.name, toggle]
  );

  return (
    <>
      {hasGroupTitle && (
        <ContainerComponent>
          <div
            className={
              'snap-y flex flex-row justify-between w-full pl-3 pr-1 text-text-primary items-center'
            }
            data-testid={`${group?.name}-header`}
          >
            <Typography
              className="groupText scroll-m-12 snap-start text-base font-medium"
              variant="h6"
              {...group?.titleAttributes}
              data-section-group-form-id={group?.name}
            >
              {t(group?.name as string)}
            </Typography>
            {group?.EndIcon && (
              <Tooltip
                classes={{
                  tooltip: 'max-w-120'
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
