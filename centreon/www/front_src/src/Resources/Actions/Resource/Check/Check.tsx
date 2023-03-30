import { RefObject, useRef } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconArrow from '@mui/icons-material/KeyboardArrowDownOutlined';
import { useMediaQuery, useTheme } from '@mui/material';
import ButtonGroup from '@mui/material/ButtonGroup';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';

import { IconButton } from '@centreon/ui';

import ResourceActionButton from '../ResourceActionButton';
import useMediaQueryListing from '../useMediaQueryListing';

import CheckOptionsList from './CheckOptionsList';

const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center'
  },
  condensed: {
    marginRight: theme.spacing(2)
  },
  container: {
    '& .MuiButton-root': {
      backgroundColor: 'transparent',
      minWidth: theme.spacing(16)
    },
    backgroundColor: theme.palette.primary.main
  },
  disabled: {
    backgroundColor: theme.palette.action.disabledBackground
  },
  iconArrow: {
    color: 'white'
  }
}));

const defaultDisabledList = { disableCheck: false, disableForcedCheck: false };

interface ClickList {
  onClickCheck: () => void;
  onClickForcedCheck: () => void;
}
interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

interface Props {
  anchorEl?: HTMLElement | null;
  disabledButton: boolean;
  disabledList?: Disabled;
  icon: JSX.Element;
  isActionPermitted: boolean;
  isDefaultChecked?: boolean;
  labelButton: string;
  onClickActionButton: () => void;
  onClickIconArrow: (event) => void;
  onClickList?: ClickList;
  testId: string;
}

const Check = ({
  disabledButton,
  disabledList = defaultDisabledList,
  labelButton,
  isActionPermitted,
  testId,
  onClickList,
  onClickActionButton,
  onClickIconArrow,
  icon,
  anchorEl,
  isDefaultChecked = false
}: Props): JSX.Element | null => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();
  const buttonGroupReference = useRef<HTMLDivElement>();

  const { applyBreakPoint } = useMediaQueryListing();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1100))) || applyBreakPoint;

  return (
    <>
      <ButtonGroup
        className={cx(classes.buttonGroup, {
          [classes.container]: !displayCondensed,
          [classes.disabled]: disabledButton && !displayCondensed,
          [classes.condensed]: displayCondensed
        })}
        ref={buttonGroupReference as RefObject<HTMLDivElement>}
      >
        <ResourceActionButton
          disabled={disabledButton}
          icon={icon}
          label={t(labelButton)}
          permitted={isActionPermitted}
          testId={testId}
          onClick={onClickActionButton}
        />
        <IconButton
          ariaLabel="arrow"
          className={cx({ [classes.iconArrow]: !displayCondensed })}
          disabled={disabledButton}
          onClick={onClickIconArrow}
        >
          {displayCondensed ? (
            <ArrowDropDownIcon fontSize="small" />
          ) : (
            <IconArrow />
          )}
        </IconButton>
      </ButtonGroup>
      <CheckOptionsList
        anchorEl={anchorEl}
        buttonGroupReference={buttonGroupReference}
        disabled={disabledList}
        isDefaultChecked={isDefaultChecked}
        open={Boolean(anchorEl)}
        onClickCheck={onClickList?.onClickCheck}
        onClickForcedCheck={onClickList?.onClickForcedCheck}
      />
    </>
  );
};

export default Check;
