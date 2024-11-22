import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import ModeEditIcon from '@mui/icons-material/ModeEditOutlineOutlined';
import VisibilityIcon from '@mui/icons-material/VisibilityOutlined';
import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { DashboardRole } from '../../../../api/models';
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

  if (equals(role, DashboardRole.editor)) {
    return (
      <Tooltip label={t(labelEditor)}>
        <ModeEditIcon className={classes.icon} />
      </Tooltip>
    );
  }

  if (equals(role, DashboardRole.viewer)) {
    return (
      <Tooltip label={t(labelViewer)}>
        <VisibilityIcon className={classes.icon} />
      </Tooltip>
    );
  }

  return <Box />;
};

export default Role;
