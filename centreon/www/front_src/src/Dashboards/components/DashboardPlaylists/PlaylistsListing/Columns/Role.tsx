import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import VisibilityIcon from '@mui/icons-material/VisibilityOutlined';
import ModeEditIcon from '@mui/icons-material/ModeEditOutlineOutlined';
import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { Role as RoleType } from '../models';
import { labelEditor, labelViewer } from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles';

const Role = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();
  const { t } = useTranslation();
  const role = row?.role;

  const isNestedRow = !isNil(role);

  if (!isNestedRow) {
    return <Box className={classes.line}>-</Box>;
  }

  if (equals(role, RoleType.Editor)) {
    return (
      <Tooltip label={t(labelEditor)}>
        <ModeEditIcon className={classes.icon} />
      </Tooltip>
    );
  }

  if (equals(role, RoleType.Viewer)) {
    return (
      <Tooltip label={t(labelViewer)}>
        <VisibilityIcon className={classes.icon} />
      </Tooltip>
    );
  }

  return <Box />;
};

export default Role;
