import { equals, isNil, path, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useSetAtom } from 'jotai';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconAcknowledge from '@mui/icons-material/Person';

import type { ComponentColumnProps } from '@centreon/ui';
import {
  DowntimeIcon,
  IconButton,
  SeverityCode,
  StatusChip,
  useStyleTable
} from '@centreon/ui';

import { forcedCheckInlineEndpointAtom } from '../../Actions/Resource/Check/checkAtoms';
import useAclQuery from '../../Actions/Resource/aclQuery';
import {
  labelAcknowledge,
  labelActionNotPermitted,
  labelForcedCheck,
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

  const setForcedCheckInlineEndpoint = useSetAtom(
    forcedCheckInlineEndpointAtom
  );

  const { canAcknowledge, canDowntime } = useAclQuery();

  const isResourceOk = pathEq(
    SeverityCode.OK,
    ['status', 'severity_code'],
    row
  );

  const isAcknowledePermitted = canAcknowledge([row]);
  const isDowntimePermitted = canDowntime([row]);

  const isForcedCheckPermitted = !isNil(
    path(['links', 'endpoints', 'forced_check'], row)
  );

  const disableAcknowledge = !isAcknowledePermitted || isResourceOk;
  const disableDowntime = !isDowntimePermitted;
  const disableForcedCheck = !isForcedCheckPermitted;

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
        <DowntimeIcon fontSize="small" />
      </IconButton>

      <IconButton
        ariaLabel={`${t(labelForcedCheck)} ${row.name}`}
        data-testid={`${labelForcedCheck} ${row.name}`}
        disabled={disableForcedCheck}
        size="large"
        title={getActionTitle({
          isActionPermitted: isForcedCheckPermitted,
          labelAction: labelForcedCheck
        })}
        onClick={(): void => {
          const forcedCheckEndpoint = path(
            ['links', 'endpoints', 'forced_check'],
            row
          );
          setForcedCheckInlineEndpoint(forcedCheckEndpoint);

          actions.onCheck(row);
        }}
      >
        <IconForcedCheck fontSize="small" />
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

    const label = equals(SeverityCode[5], statusName) ? (
      <>{t(statusName)}</>
    ) : (
      t(statusName)
    );

    return (
      <div className={classes.statusColumn}>
        {isHovered ? (
          <StatusColumnOnHover actions={actions} row={row} />
        ) : (
          <StatusChip
            className={classes.statusColumnChip}
            label={label}
            severityCode={row.status.severity_code}
          />
        )}
      </div>
    );
  };

  return Status;
};

export default StatusColumn;
