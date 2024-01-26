import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import Icon from '@mui/icons-material/People';
import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { DashboardRole, ContactType } from '../../../../api/models';
import { labelShares } from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles';

const Share = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();
  const { t } = useTranslation();
  const { shares, role, name, type, ownRole } = row;

  if (equals(ownRole, DashboardRole.viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

  const isNestedRow = !isNil(role);

  const sharesNubmer = shares?.length;

  if (!isNestedRow) {
    return <Box>{`${sharesNubmer} ${t(labelShares.toLowerCase())}`}</Box>;
  }

  if (equals(type, ContactType.contactGroup)) {
    return (
      <Box>
        <Icon className={classes.icon} />
        <Box className={classes.contactGroups}>{name}</Box>
      </Box>
    );
  }

  return <Box>{name}</Box>;
};

export default Share;
