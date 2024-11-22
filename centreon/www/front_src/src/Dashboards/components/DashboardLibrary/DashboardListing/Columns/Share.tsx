import { equals, isNil } from 'ramda';

import Icon from '@mui/icons-material/People';
import { Box } from '@mui/material';

import { ComponentColumnProps, usePluralizedTranslation } from '@centreon/ui';

import { ContactType, DashboardRole } from '../../../../api/models';
import { labelShares } from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles';

const Share = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();
  const { pluralizedT } = usePluralizedTranslation();

  const { shares, role, name, type, ownRole } = row;

  if (equals(ownRole, DashboardRole.viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

  const isNestedRow = !isNil(role);

  const sharesCount = shares?.length || 0;

  if (!isNestedRow) {
    return (
      <Box>
        {`${sharesCount} ${pluralizedT({
          count: sharesCount,
          label: labelShares
        }).toLowerCase()}`}
      </Box>
    );
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
