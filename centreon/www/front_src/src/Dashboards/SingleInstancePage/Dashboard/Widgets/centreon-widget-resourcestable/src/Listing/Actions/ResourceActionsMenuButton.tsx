import IconActions from '@mui/icons-material/Bolt';
import IconArrowDown from '@mui/icons-material/KeyboardArrowDownOutlined';
import {
  Box,
  Button,
  ClickAwayListener,
  Divider,
  MenuList,
  Paper,
  Popper
} from '@mui/material';

import { IconButton } from '@centreon/ui';

import { Fragment, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { labelActions } from '../translatedLabels';
import ActionMenuItem from './ActionMenuItem';
import IconArrow from './Check/IconArrow';

const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center',
    display: 'flex'
  },
  // Split-pill: main button left-rounded/right-flat, chevron mirrored.
  container: {
    '& .MuiButton-root': {
      borderRadius: '18px 4px 4px 18px',
      whiteSpace: 'nowrap'
    },
    gap: theme.spacing(0.5)
  },
  iconArrow: {
    '&:hover': {
      backgroundColor: theme.palette.primary.dark
    },
    '&.Mui-disabled': {
      backgroundColor: theme.palette.action.disabledBackground
    },
    backgroundColor: theme.palette.primary.main,
    borderRadius: '4px 18px 18px 4px',
    color: theme.palette.common.white,
    height: theme.spacing(4.5),
    padding: 0,
    width: theme.spacing(4.5)
  }
}));

export interface ResourceActionItem {
  description?: string;
  disabled: boolean;
  label: string;
  onClick: () => void;
  permitted: boolean;
  testId: string;
}

interface Props {
  actionGroups: Array<Array<ResourceActionItem>>;
}

const ResourceActionsMenuButton = ({ actionGroups }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const anchorRef = useRef<HTMLDivElement>(null);
  const [isOpen, setIsOpen] = useState(false);

  const toggle = (): void => setIsOpen((previous) => !previous);
  const close = (): void => setIsOpen(false);

  const groups = actionGroups.filter((group) => group.length > 0);

  return (
    <ClickAwayListener onClickAway={close}>
      <Box
        className={cx(classes.buttonGroup, classes.container)}
        ref={anchorRef}
      >
        <Button
          aria-haspopup="menu"
          color="primary"
          data-testid="resourceActionsMenu"
          onClick={toggle}
          size="small"
          startIcon={<IconActions />}
          variant="contained"
        >
          {t(labelActions)}
        </Button>
        <IconButton
          ariaLabel={t(labelActions) as string}
          className={classes.iconArrow}
          onClick={toggle}
        >
          <IconArrow icon={<IconArrowDown />} open={isOpen} />
        </IconButton>
        <Popper
          anchorEl={anchorRef.current}
          modifiers={[{ name: 'offset', options: { offset: [0, 4] } }]}
          open={isOpen}
          placement="bottom-start"
          sx={{ zIndex: (theme) => theme.zIndex.fab }}
        >
          <Paper elevation={3} variant="elevation">
            <MenuList>
              {groups.map((group, index) => (
                // biome-ignore lint/suspicious/noArrayIndexKey: stable group order
                <Fragment key={`action-group-${index}`}>
                  {index > 0 && <Divider />}
                  {group.map((action) => (
                    <ActionMenuItem
                      description={action.description}
                      disabled={action.disabled}
                      key={action.testId}
                      label={action.label}
                      onClick={(): void => {
                        action.onClick();
                        close();
                      }}
                      permitted={action.permitted}
                      testId={action.testId}
                    />
                  ))}
                </Fragment>
              ))}
            </MenuList>
          </Paper>
        </Popper>
      </Box>
    </ClickAwayListener>
  );
};

export default ResourceActionsMenuButton;
