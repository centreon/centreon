import { pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconAcknowledge from '@mui/icons-material/Person';
import IconCheck from '@mui/icons-material/Sync';

import {
  SeverityCode,
  StatusChip,
  IconButton,
  useStyleTable
} from '@centreon/ui';
import type { ComponentColumnProps } from '@centreon/ui';

import useAclQuery from '../../Actions/Resource/aclQuery';
import IconDowntime from '../../icons/Downtime';
import {
  labelAcknowledge,
  labelActionNotPermitted,
  labelCheck,
  labelSetDowntime,
  labelSetDowntimeOn
} from '../../translatedLabels';

import { ColumnProps } from '.';

interface StylesProps {
  data: {
    height: number;
    width: number;
  };
}

const useStyles = makeStyles<StylesProps>()((theme, { data }) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'nowrap',
    gridGap: theme.spacing(0.25),
    justifyContent: 'center'
  },
  statusColumn: {
    alignItems: 'center',
    display: 'flex',
    width: '100%'
  },
  statusColumnChip: {
    fontWeight: 'bold',
    height: data.height,
    marginLeft: 1,
    minWidth: theme.spacing((data.width - 1) / 8),
    width: '100%'
  }
}));

type StatusColumnProps = {
  actions;
} & Pick<ComponentColumnProps, 'row'>;

const StatusColumnOnHover = ({
  actions,
  row
}: StatusColumnProps): JSX.Element => {
  const { dataStyle } = useStyleTable({});
  const { classes } = useStyles({ data: dataStyle.statusColumnChip });
  const { t } = useTranslation();

  const { canAcknowledge, canDowntime, canCheck } = useAclQuery();

  const isResourceOk = pathEq(
    ['status', 'severity_code'],
    SeverityCode.Ok,
    row
  );

  const isAcknowledePermitted = canAcknowledge([row]);
  const isDowntimePermitted = canDowntime([row]);
  const isCheckPermitted = canCheck([row]);

  const disableAcknowledge = !isAcknowledePermitted || isResourceOk;
  const disableDowntime = !isDowntimePermitted;
  const disableCheck = !isCheckPermitted;

  const getActionTitle = ({ labelAction, isActionPermitted }): string => {
    const translatedLabelAction = t(labelAction);

    return isActionPermitted
      ? translatedLabelAction
      : `${translatedLabelAction} (${t(labelActionNotPermitted)})`;
  };

  return (
    <div className={classes.actions}>
      <IconButton
        ariaLabel={`${t(labelAcknowledge)} ${row.name}`}
        color="primary"
        data-testid={`${labelAcknowledge} ${row.name}`}
        disabled={disableAcknowledge}
        size="large"
        title={getActionTitle({
          isActionPermitted: isAcknowledePermitted,
          labelAction: labelAcknowledge
        })}
        onClick={(): void => actions.onAcknowledge(row)}
      >
        <IconAcknowledge fontSize="small" />
      </IconButton>
      <IconButton
        ariaLabel={`${t(labelSetDowntimeOn)} ${row.name}`}
        data-testid={`${labelSetDowntimeOn} ${row.name}`}
        disabled={disableDowntime}
        size="large"
        title={getActionTitle({
          isActionPermitted: isDowntimePermitted,
          labelAction: labelSetDowntime
        })}
        onClick={(): void => actions.onDowntime(row)}
      >
        <IconDowntime fontSize="small" />
      </IconButton>
      <IconButton
        ariaLabel={`${t(labelCheck)} ${row.name}`}
        data-testid={`${labelCheck} ${row.name}`}
        disabled={disableCheck}
        size="large"
        title={getActionTitle({
          isActionPermitted: isCheckPermitted,
          labelAction: labelCheck
        })}
        onClick={(): void => actions.onCheck(row)}
      >
        <IconCheck fontSize="small" />
      </IconButton>
    </div>
  );
};

const StatusColumn = ({
  actions,
  t
}: ColumnProps): ((props: ComponentColumnProps) => JSX.Element) => {
  const Status = ({ row, isHovered }: ComponentColumnProps): JSX.Element => {
    const { dataStyle } = useStyleTable({});
    const { classes } = useStyles({
      data: dataStyle.statusColumnChip
    });

    const statusName = row.status.name;

    return (
      <div className={classes.statusColumn}>
        {isHovered ? (
          <StatusColumnOnHover actions={actions} row={row} />
        ) : (
          <StatusChip
            className={classes.statusColumnChip}
            label={t(statusName)}
            severityCode={row.status.severity_code}
          />
        )}
      </div>
    );
  };

  return Status;
};

export default StatusColumn;
