import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import Icon from '@mui/icons-material/People';
import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { Role as RoleType, ShareType } from '../models';
import { labelShares } from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles';

const Share = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();
  const { t } = useTranslation();
  const { shares, role, shareName, type, ownRole } = row;

  if (equals(ownRole, RoleType.Viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

  const isNestedRow = !isNil(role);

  const sharesNubmer = shares?.length;

  if (!isNestedRow) {
    return <Box>{`${sharesNubmer} ${t(labelShares.toLowerCase())}`}</Box>;
  }

  if (equals(type, ShareType.ContactGroup)) {
    return (
      <Box>
        <Icon className={classes.icon} />
        <Box className={classes.contactGroups}>{shareName}</Box>
      </Box>
    );
  }

  return <Box>{shareName}</Box>;
};

export default Share;
