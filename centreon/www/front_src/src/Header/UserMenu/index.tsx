import { MouseEvent, RefObject, useEffect, useRef, useState } from 'react';

import { __, equals, gt, isNil, not } from 'ramda';
import { useTranslation, withTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import CheckIcon from '@mui/icons-material/Check';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import FullscreenIcon from '@mui/icons-material/Fullscreen';
import LogoutIcon from '@mui/icons-material/Logout';
import UserIcon from '@mui/icons-material/Person';
import SettingsIcon from '@mui/icons-material/Settings';
import {
  Badge,
  Box,
  Fade,
  List,
  ListItem,
  ListItemButton,
  ListItemText,
  Popper,
  Tooltip
} from '@mui/material';

import {
  MenuSkeleton,
  getData,
  useFullscreen,
  useLocaleDateTimeFormat,
  useRequest
} from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

import useNavigation from '../../Navigation/useNavigation';
import routeMap from '../../reactRoutes/routeMap';
import Clock from '../Clock';
import { userEndpoint } from '../api/endpoints';

import SwitchMode from './SwitchThemeMode';
import {
  labelCopyAutologinLink,
  labelEditProfile,
  labelFullscreen,
  labelLogout,
  labelPasswordWillExpireIn,
  labelProfile
} from './translatedLabels';

const editProfileTopologyPage = '50104';
const sevenDays = 60 * 60 * 24 * 7;
const isGreaterThanSevenDays = gt(__, sevenDays);

interface UserData {
  autologinkey: string | null;
  fullname: string | null;
  hasAccessToProfile: boolean;
  locale: string | null;
  password_remaining_time?: number | null;
  soundNotificationsEnabled: boolean;
  timezone: string | null;
  userId: string | null;
  username: string | null;
}

const useStyles = makeStyles()((theme) => ({
  badge: {
    alignItems: 'center',
    borderRadius: theme.spacing(1.25),
    display: 'flex',
    fontSize: theme.typography.body1.fontSize,
    height: theme.spacing(2.5),
    justifyContent: 'spaceBetween',
    minWidth: theme.spacing(2.5)
  },
  clock: {
    display: 'none',
    [theme.breakpoints.up(648)]: {
      display: 'block',
      textAlign: 'right'
    }
  },
  containerList: {
    color: theme.palette.text.primary,
    padding: 0
  },
  fullname: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  hiddenInput: {
    height: theme.spacing(0),
    opacity: 0,
    position: 'absolute',
    top: theme.spacing(-13),
    width: theme.spacing(0)
  },
  icon: {
    marginRight: theme.spacing(1)
  },
  icons: {
    borderLeft: `1px solid ${theme.palette.common.white}`,
    paddingLeft: theme.spacing(3)
  },
  listItem: {
    '&:first-of-type': {
      borderBottom: `1px solid ${theme.palette.divider}`
    },
    '&:hover': {
      background: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.primary.dark
        : theme.palette.primary.light,
      color: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.common.white
        : theme.palette.primary.main
    },
    '&:last-of-type': {
      borderTop: `1px solid ${theme.palette.divider}`
    },
    padding: theme.spacing(1)
  },
  listItemButton: {
    '&:hover': {
      background: 'none'
    },
    padding: 0
  },
  menu: {
    backgroundColor: theme.palette.background.default,
    border: 'none',
    borderRadius: 0,
    boxShadow: theme.shadows[3],
    fontSize: theme.typography.body2.fontSize,
    minWidth: 190
  },
  passwordExpiration: {
    color: theme.palette.warning.main
  },
  popper: {
    zIndex: theme.zIndex.tooltip
  },
  switchItem: {
    padding: theme.spacing(0, 2, 0.25, 11 / 8)
  },
  text: {
    margin: 0,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  userIcon: {
    color: theme.palette.common.white,
    cursor: 'pointer',
    fontSize: theme.spacing(4)
  },
  wrapper: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(3),
    height: '100%',
    justifyContent: 'flex-end'
  }
}));
interface Props {
  headerRef?: RefObject<HTMLElement>;
}

export const testUtils = {
  useNavigate,
  useNavigation
};

const UserMenu = ({ headerRef }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { allowedPages } = testUtils.useNavigation();

  const [copied, setCopied] = useState(false);
  const [data, setData] = useState<UserData | null>(null);
  const [anchorEl, setAnchorEl] = useState<SVGSVGElement | null>(null);
  const [anchorHeight, setAnchorHeight] = useState(12);
  const profile = useRef<HTMLDivElement>();
  const userMenu = useRef<HTMLDivElement>();
  const autologinNode = useRef<HTMLTextAreaElement>();
  const refreshTimeout = useRef<NodeJS.Timeout>();
  const userIconRef = useRef<SVGSVGElement | null>(null);
  const { sendRequest } = useRequest<UserData>({
    request: getData
  });

  const navigate = testUtils.useNavigate();
  const { toHumanizedDuration } = useLocaleDateTimeFormat();
  const { toggleFullscreen } = useFullscreen();

  const loadUserData = (): void => {
    sendRequest({ endpoint: userEndpoint })
      .then((retrievedUserData) => {
        setData(retrievedUserData);
        refreshData();
      })
      .catch((error) => {
        if (error.response && error.response.status === 401) {
          setData(null);
        }
      });
  };

  const logout = (): void => {
    navigate(routeMap.logout);
  };

  const refreshData = (): void => {
    if (refreshTimeout.current) {
      clearTimeout(refreshTimeout.current);
    }
    refreshTimeout.current = setTimeout(() => {
      loadUserData();
    }, 60000);
  };

  const getPositionOfPopper = (): void => {
    if (isNil(headerRef?.current) || isNil(userIconRef?.current)) {
      return;
    }
    const headerHeight = headerRef?.current?.getBoundingClientRect()?.height;

    const userMenuBottom =
      userIconRef?.current?.getBoundingClientRect()?.bottom;

    if (isNil(headerHeight)) {
      return;
    }
    setAnchorHeight(headerHeight - userMenuBottom);
  };

  const toggle = (event: MouseEvent<SVGSVGElement>): void => {
    if (anchorEl) {
      setAnchorEl(null);

      return;
    }
    setAnchorEl(event.currentTarget);
    getPositionOfPopper();
  };

  const closeUserMenu = (): void => {
    setAnchorEl(null);
  };

  const onCopy = (): void => {
    if (autologinNode?.current) {
      autologinNode.current.select();
      window.document.execCommand('copy');
      setCopied(true);
      setTimeout(() => {
        setCopied(false);
      }, 700);
    }
  };

  const handleClick = (e): void => {
    const isProfileClicked =
      !profile.current || profile.current.contains(e.target);
    const isUserMenuClicked =
      !userMenu.current || userMenu.current.contains(e.target);

    if (isProfileClicked || isUserMenuClicked) {
      return;
    }
    setAnchorEl(null);
  };

  const navigateToUserSettingsAndCloseUserMenu = (): void => {
    navigate(`/main.php?p=${editProfileTopologyPage}&o=c`);
    closeUserMenu();
  };

  const logoutFromSession = (e: MouseEvent): void => {
    e.preventDefault();
    logout();
  };

  useEffect(() => {
    window.addEventListener('mousedown', handleClick, false);
    window.addEventListener('resize', getPositionOfPopper);

    loadUserData();

    return (): void => {
      window.removeEventListener('mousedown', handleClick, false);
      window.removeEventListener('resize', getPositionOfPopper);

      if (refreshTimeout.current) {
        clearTimeout(refreshTimeout.current);
      }
    };
  }, []);

  if (!data) {
    return <MenuSkeleton width={24} />;
  }

  const allowEditProfile = allowedPages?.includes(editProfileTopologyPage);

  const gethref = window.location.href;
  const conditionnedhref = gethref + (window.location.search ? '&' : '?');
  const autolink = `${conditionnedhref}autologin=1&useralias=${data.username}&token=${data.autologinkey}`;

  const passwordIsNotYetAboutToExpire =
    isNil(data.password_remaining_time) ||
    isGreaterThanSevenDays(data.password_remaining_time);

  const formattedPasswordRemainingTime = toHumanizedDuration(
    data.password_remaining_time as number
  );

  const primaryTypographyProps = {
    className: classes.text
  };

  const changeFullscreen = (): void => {
    setAnchorEl(null);
    toggleFullscreen(document.querySelector('body'));
  };

  return (
    <div className={classes.wrapper} ref={profile as RefObject<HTMLDivElement>}>
      <div className={classes.clock}>
        <Clock />
      </div>
      <div className={classes.icons}>
        <Tooltip
          placement="bottom-end"
          title={
            passwordIsNotYetAboutToExpire
              ? ''
              : `${t(
                  labelPasswordWillExpireIn
                )}: ${formattedPasswordRemainingTime}`
          }
        >
          <Badge
            color="warning"
            invisible={passwordIsNotYetAboutToExpire}
            variant="dot"
          >
            <UserIcon
              aria-label={t(labelProfile)}
              className={classes.userIcon}
              data-cy="userIcon"
              fontSize="large"
              ref={userIconRef}
              onClick={toggle}
            />
          </Badge>
        </Tooltip>
        <Popper
          transition
          anchorEl={anchorEl}
          className={classes.popper}
          data-cy="popper"
          modifiers={[
            {
              name: 'offset',
              options: {
                offset: [0, anchorHeight]
              }
            }
          ]}
          open={not(isNil(anchorEl))}
          placement="bottom-end"
        >
          {({ TransitionProps }): JSX.Element => (
            <Fade {...TransitionProps} timeout={350}>
              <Box
                className={classes.menu}
                ref={userMenu as RefObject<HTMLDivElement>}
                sx={{
                  display: isNil(anchorEl) ? 'none' : 'block'
                }}
              >
                <List dense className={classes.containerList}>
                  <ListItem className={classes.listItem}>
                    <ListItemText
                      primaryTypographyProps={primaryTypographyProps}
                    >
                      {data.username}
                    </ListItemText>
                  </ListItem>

                  {not(passwordIsNotYetAboutToExpire) && (
                    <ListItem
                      className={`${classes.listItem} ${classes.passwordExpiration}`}
                    >
                      {`${t(labelPasswordWillExpireIn)}: `}
                      {formattedPasswordRemainingTime}
                    </ListItem>
                  )}
                  {allowEditProfile && (
                    <ListItem className={classes.listItem}>
                      <ListItemButton
                        className={classes.listItemButton}
                        onClick={navigateToUserSettingsAndCloseUserMenu}
                      >
                        <SettingsIcon
                          className={classes.icon}
                          fontSize="small"
                        />
                        <ListItemText
                          primaryTypographyProps={primaryTypographyProps}
                        >
                          {t(labelEditProfile)}
                        </ListItemText>
                      </ListItemButton>
                    </ListItem>
                  )}
                  {data.autologinkey && (
                    <ListItem className={classes.listItem}>
                      <ListItemButton
                        className={classes.listItemButton}
                        onClick={onCopy}
                      >
                        {copied ? (
                          <CheckIcon
                            className={classes.icon}
                            fontSize="small"
                          />
                        ) : (
                          <FileCopyIcon
                            className={classes.icon}
                            fontSize="small"
                          />
                        )}
                        <ListItemText
                          primaryTypographyProps={primaryTypographyProps}
                        >
                          {t(labelCopyAutologinLink)}
                        </ListItemText>
                      </ListItemButton>
                      <textarea
                        readOnly
                        className={cx(classes.hiddenInput)}
                        id="autologin-input"
                        ref={autologinNode as RefObject<HTMLTextAreaElement>}
                        value={autolink}
                      />
                    </ListItem>
                  )}
                  <ListItem className={classes.listItem}>
                    <SwitchMode />
                  </ListItem>
                  <ListItem className={classes.listItem}>
                    <ListItemButton
                      className={classes.listItemButton}
                      onClick={changeFullscreen}
                    >
                      <FullscreenIcon
                        className={classes.icon}
                        fontSize="small"
                      />
                      <ListItemText
                        primaryTypographyProps={primaryTypographyProps}
                      >
                        {t(labelFullscreen)}
                      </ListItemText>
                    </ListItemButton>
                  </ListItem>
                  <ListItem className={classes.listItem}>
                    <ListItemButton
                      className={classes.listItemButton}
                      onClick={logoutFromSession}
                    >
                      <LogoutIcon className={classes.icon} fontSize="small" />
                      <ListItemText
                        primaryTypographyProps={primaryTypographyProps}
                      >
                        {t(labelLogout)}
                      </ListItemText>
                    </ListItemButton>
                  </ListItem>
                </List>
              </Box>
            </Fade>
          )}
        </Popper>
      </div>
    </div>
  );
};

export default withTranslation()(UserMenu);
