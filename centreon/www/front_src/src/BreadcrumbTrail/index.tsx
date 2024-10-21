import { useCallback, useMemo, useState } from 'react';

import { useAtomValue } from 'jotai';
import { isNil, pluck } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import NavigateNextIcon from '@mui/icons-material/NavigateNext';
import { Box, Breadcrumbs as MuiBreadcrumbs } from '@mui/material';

import navigationAtom from '../Navigation/navigationAtoms';

import { useCopyToClipboard } from '@centreon/ui';
import { IconButton, Tooltip } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router';
import Breadcrumb from './Breadcrumb';
import getBreadcrumbsByPath from './getBreadcrumbsByPath';
import { Breadcrumb as BreadcrumbModel, BreadcrumbsByPath } from './models';
import {
  labelBreadcrumbCopied,
  labelCopyBreadcrumb,
  labelFailedToCopyBreadcrumb
} from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  item: {
    display: 'flex'
  },
  root: {
    padding: theme.spacing(0.5, 0, 0.5, 3)
  },
  breadcrumbCopyIcon: {
    '&[data-is-hovered="true"]': {
      opacity: 1
    },
    opacity: 0
  }
}));

interface Props {
  breadcrumbsByPath: BreadcrumbsByPath;
  path: string;
}

const getBreadcrumbs = ({
  breadcrumbsByPath,
  path
}): Array<BreadcrumbModel> => {
  if (breadcrumbsByPath[path]) {
    return breadcrumbsByPath[path];
  }

  if (path.includes('/')) {
    const shorterPath = path.split('/').slice(0, -1).join('/');

    return getBreadcrumbs({ breadcrumbsByPath, path: shorterPath });
  }

  return [];
};

const BreadcrumbTrail = ({ breadcrumbsByPath, path }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const [isHovered, setIsHovered] = useState(false);

  const { copy } = useCopyToClipboard({
    successMessage: t(labelBreadcrumbCopied),
    errorMessage: t(labelFailedToCopyBreadcrumb)
  });

  const breadcrumbs = useMemo(
    () => getBreadcrumbs({ breadcrumbsByPath, path }),
    [breadcrumbsByPath, path]
  );

  const hover = useCallback(() => setIsHovered(true), []);
  const leave = useCallback(() => setIsHovered(false), []);

  const copyBreadcrumb = useCallback(() => {
    const breadcrumbString = pluck('label', breadcrumbs).join(' > ');
    copy(breadcrumbString);
  }, [breadcrumbs]);

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'row',
        gap: 1,
        width: 'fit-content'
      }}
      onMouseEnter={hover}
      onMouseLeave={leave}
    >
      <MuiBreadcrumbs
        aria-label="Breadcrumb"
        classes={{ li: classes.item, root: classes.root }}
        separator={<NavigateNextIcon fontSize="small" />}
      >
        {breadcrumbs.map((breadcrumb, index) => (
          <Breadcrumb
            breadcrumb={breadcrumb}
            key={breadcrumb.label}
            last={index === breadcrumbs.length - 1}
          />
        ))}
      </MuiBreadcrumbs>
      <Tooltip label={t(labelCopyBreadcrumb)} followCursor={false}>
        <IconButton
          size="small"
          onClick={copyBreadcrumb}
          icon={<ContentCopyIcon fontSize="small" color="primary" />}
          data-is-hovered={isHovered}
          className={classes.breadcrumbCopyIcon}
          sx={{
            transition: 'all 175ms ease-out'
          }}
        />
      </Tooltip>
    </Box>
  );
};

export const router = {
  useLocation
};

const Breadcrumbs = (): JSX.Element | null => {
  const navigation = useAtomValue(navigationAtom);
  const { pathname } = router.useLocation();

  if (isNil(navigation)) {
    return null;
  }

  return (
    <BreadcrumbTrail
      breadcrumbsByPath={getBreadcrumbsByPath(navigation.result)}
      path={pathname}
    />
  );
};

export default Breadcrumbs;
