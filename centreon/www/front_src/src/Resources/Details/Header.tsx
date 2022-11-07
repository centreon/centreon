<<<<<<< HEAD
=======
/* eslint-disable hooks/sort */
// Issue : https://github.com/hiukky/eslint-plugin-hooks/issues/3

import * as React from 'react';

>>>>>>> centreon/dev-21.10.x
import { useTranslation } from 'react-i18next';
import { hasPath, isNil, not, path, prop } from 'ramda';

import {
  Grid,
  Typography,
<<<<<<< HEAD
  Theme,
  Link,
  Tooltip,
  Skeleton,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import CopyIcon from '@mui/icons-material/FileCopy';
import SettingsIcon from '@mui/icons-material/Settings';
import { CreateCSSProperties } from '@mui/styles';
=======
  makeStyles,
  Theme,
  Link,
  Tooltip,
} from '@material-ui/core';
import { Skeleton } from '@material-ui/lab';
import CopyIcon from '@material-ui/icons/FileCopy';
import SettingsIcon from '@material-ui/icons/Settings';
import { CreateCSSProperties } from '@material-ui/styles';
>>>>>>> centreon/dev-21.10.x

import {
  StatusChip,
  SeverityCode,
  IconButton,
  useSnackbar,
  copyToClipboard,
} from '@centreon/ui';

import {
  labelActionNotPermitted,
  labelConfigure,
  labelCopyLink,
  labelLinkCopied,
  labelShortcuts,
  labelSomethingWentWrong,
} from '../translatedLabels';
<<<<<<< HEAD
=======
import memoizeComponent from '../memoizedComponent';
>>>>>>> centreon/dev-21.10.x
import { Parent, ResourceUris } from '../models';

import SelectableResourceName from './tabs/Details/SelectableResourceName';
import ShortcutsTooltip from './ShortcutsTooltip';

import { DetailsSectionProps } from '.';

interface MakeStylesProps {
  displaySeverity: boolean;
}

const useStyles = makeStyles<Theme, MakeStylesProps>((theme) => ({
  header: ({ displaySeverity }): CreateCSSProperties<MakeStylesProps> => ({
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: `${
      displaySeverity ? 'auto' : ''
    } auto minmax(0, 1fr) auto auto`,
    height: 43,
    padding: theme.spacing(0, 1),
  }),
<<<<<<< HEAD
=======
}));

const useStylesHeaderContent = makeStyles((theme) => ({
>>>>>>> centreon/dev-21.10.x
  parent: {
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(1),
    gridTemplateColumns: 'auto minmax(0, 1fr)',
  },
  resourceName: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'minmax(auto, min-content) min-content',
    height: '100%',
  },
  resourceNameConfigurationIcon: {
    alignSelf: 'center',
    display: 'flex',
    minWidth: theme.spacing(2.5),
  },
  resourceNameConfigurationLink: {
    height: theme.spacing(2.5),
  },
  resourceNameContainer: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    width: '100%',
  },
  resourceNameTooltip: {
    maxWidth: 'none',
  },
  truncated: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
}));

const LoadingSkeleton = (): JSX.Element => (
  <Grid container item alignItems="center" spacing={2} style={{ flexGrow: 1 }}>
    <Grid item>
<<<<<<< HEAD
      <Skeleton height={25} variant="circular" width={25} />
=======
      <Skeleton height={25} variant="circle" width={25} />
>>>>>>> centreon/dev-21.10.x
    </Grid>
    <Grid item>
      <Skeleton height={25} width={250} />
    </Grid>
  </Grid>
);

type Props = {
  onSelectParent: (parent: Parent) => void;
} & DetailsSectionProps;

<<<<<<< HEAD
const Header = ({ details, onSelectParent }: Props): JSX.Element => {
  const classes = useStyles({
    displaySeverity: not(isNil(details?.severity_level)),
  });
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();
=======
const HeaderContent = ({ details, onSelectParent }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();
  const classes = useStylesHeaderContent();
>>>>>>> centreon/dev-21.10.x

  const copyResourceLink = (): void => {
    try {
      copyToClipboard(window.location.href);
      showSuccessMessage(t(labelLinkCopied));
    } catch (_) {
      showErrorMessage(t(labelSomethingWentWrong));
    }
  };

  if (details === undefined) {
    return <LoadingSkeleton />;
  }

  const resourceUris = path<ResourceUris>(
    ['links', 'uris'],
    details,
  ) as ResourceUris;

  const resourceConfigurationUri = prop('configuration', resourceUris);

  const resourceConfigurationUriTitle = isNil(resourceConfigurationUri)
    ? t(labelActionNotPermitted)
    : '';

  const resourceConfigurationIconColor = isNil(resourceConfigurationUri)
    ? 'disabled'
    : 'primary';

  return (
<<<<<<< HEAD
    <div className={classes.header}>
=======
    <>
>>>>>>> centreon/dev-21.10.x
      {details?.severity_level && (
        <StatusChip
          label={details?.severity_level.toString()}
          severityCode={SeverityCode.None}
        />
      )}
      <StatusChip
        label={t(details.status.name)}
        severityCode={details.status.severity_code}
      />
      <div className={classes.resourceNameContainer}>
        <div
          aria-label={`${details.name}_hover`}
          className={classes.resourceName}
        >
          <Tooltip
            classes={{ tooltip: classes.resourceNameTooltip }}
            placement="top"
            title={details.name}
          >
            <Typography className={classes.truncated}>
              {details.name}
            </Typography>
          </Tooltip>
          <Tooltip title={resourceConfigurationUriTitle}>
            <div className={classes.resourceNameConfigurationIcon}>
              <Link
                aria-label={`${t(labelConfigure)}_${details.name}`}
                className={classes.resourceNameConfigurationLink}
                data-testid={labelConfigure}
                href={resourceConfigurationUri}
              >
                <SettingsIcon
                  color={resourceConfigurationIconColor}
                  fontSize="small"
                />
              </Link>
            </div>
          </Tooltip>
        </div>
        {hasPath(['parent', 'status'], details) && (
          <div className={classes.parent}>
            <StatusChip
              severityCode={
                details.parent.status?.severity_code || SeverityCode.None
              }
            />
            <SelectableResourceName
              name={details.parent.name}
              variant="caption"
              onSelect={(): void => onSelectParent(details.parent)}
            />
          </div>
        )}
      </div>
      <ShortcutsTooltip
        data-testid={labelShortcuts}
        resourceUris={resourceUris}
      />
      <IconButton
        ariaLabel={t(labelCopyLink)}
        data-testid={labelCopyLink}
        size="small"
        title={t(labelCopyLink)}
        onClick={copyResourceLink}
      >
        <CopyIcon fontSize="small" />
      </IconButton>
<<<<<<< HEAD
=======
    </>
  );
};

const Header = ({ details, onSelectParent }: Props): JSX.Element => {
  const classes = useStyles({
    displaySeverity: not(isNil(details?.severity_level)),
  });

  return (
    <div className={classes.header}>
      <HeaderContent details={details} onSelectParent={onSelectParent} />
>>>>>>> centreon/dev-21.10.x
    </div>
  );
};

<<<<<<< HEAD
export default Header;
=======
export default memoizeComponent<Props>({
  Component: Header,
  memoProps: ['details'],
});
>>>>>>> centreon/dev-21.10.x
