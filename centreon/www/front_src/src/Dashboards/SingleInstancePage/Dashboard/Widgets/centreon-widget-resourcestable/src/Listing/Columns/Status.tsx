import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconAcknowledge from '@mui/icons-material/Person';

import type { ComponentColumnProps } from '@centreon/ui';
import {
  IconButton,
  Method,
  SeverityCode,
  StatusChip,
  useMutationQuery,
  useSnackbar,
  useStyleTable
} from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { equals, isNil, path, pathEq } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  resourcesToAcknowledgeAtom,
  resourcesToSetDowntimeAtom
} from '../../atom';
import useAclQuery from '../Actions/aclQuery';
import { DisplayType } from '../models';
import {
  labelAcknowledge,
  labelActionNotPermitted,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelSetDowntime,
  labelSetDowntimeOn
} from '../translatedLabels';
import IconDowntime from './Icons/Downtime';
import { useStyles } from './Status.styles';

const StatusColumnOnHover = ({
  row
}: Pick<ComponentColumnProps, 'row'>): ReactElement => {
  const { dataStyle } = useStyleTable({});
  const { classes } = useStyles({ data: dataStyle.statusColumnChip });
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const setResourcesToAcknowledge = useSetAtom(resourcesToAcknowledgeAtom);
  const setResourcesToSetDowntime = useSetAtom(resourcesToSetDowntimeAtom);

  const forcedCheckEndpoint = path(['links', 'endpoints', 'forced_check'], row);

  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => forcedCheckEndpoint as string,
    method: Method.POST
  });

  const forcedCheck = (): void => {
    checkResource({
      payload: {
        is_forced: true
      }
    }).then(() => {
      showSuccessMessage(t(labelForcedCheckCommandSent));
    });
  };

  const acknowledge = (): void => {
    // biome-ignore lint/suspicious/noExplicitAny: typing fallback
    setResourcesToAcknowledge([row as any]);
  };

  const setDowntime = (): void => {
    // biome-ignore lint/suspicious/noExplicitAny: typing fallback
    setResourcesToSetDowntime([row as any]);
  };

  const { canAcknowledge, canDowntime } = useAclQuery();

  const isResourceOk = pathEq(
    SeverityCode.OK,
    ['status', 'severity_code'],
    row
  );

  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const isAcknowledePermitted = canAcknowledge([row as any]);
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const isDowntimePermitted = canDowntime([row as any]);

  const isForcedCheckPermitted = !isNil(
    path(['links', 'endpoints', 'forced_check'], row)
  );

  const disableAcknowledge = !isAcknowledePermitted || isResourceOk;
  const disableDowntime = !isDowntimePermitted;
  const disableForcedCheck = !isForcedCheckPermitted;

  const getActionTitle = ({
    labelAction,
    isActionPermitted
  }: {
    labelAction: string;
    isActionPermitted: boolean;
  }): string => {
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
        onClick={acknowledge}
        size="large"
        title={getActionTitle({
          isActionPermitted: isAcknowledePermitted,
          labelAction: labelAcknowledge
        })}
      >
        <IconAcknowledge fontSize="small" />
      </IconButton>
      <IconButton
        ariaLabel={`${t(labelSetDowntimeOn)} ${row.name}`}
        data-testid={`${labelSetDowntimeOn} ${row.name}`}
        disabled={disableDowntime}
        onClick={setDowntime}
        size="large"
        title={getActionTitle({
          isActionPermitted: isDowntimePermitted,
          labelAction: labelSetDowntime
        })}
      >
        <IconDowntime fontSize="small" />
      </IconButton>

      <IconButton
        ariaLabel={`${t(labelForcedCheck)} ${row.name}`}
        data-testid={`${labelForcedCheck} ${row.name}`}
        disabled={disableForcedCheck}
        onClick={forcedCheck}
        size="large"
        title={getActionTitle({
          isActionPermitted: isForcedCheckPermitted,
          labelAction: labelForcedCheck
        })}
      >
        <IconForcedCheck fontSize="small" />
      </IconButton>
    </div>
  );
};

interface StatusColumnFactoryProps {
  displayType: DisplayType;
  classes: { statusColumn: string; statusColumnChip: string };
  t: (key: string) => string;
  isOnPublicPage?: boolean;
}

const StatusColumn = ({
  displayType,
  classes,
  t,
  isOnPublicPage
}: StatusColumnFactoryProps) => {
  return ({ row, isHovered }: ComponentColumnProps): ReactElement => {
    const typedRow = row as {
      status: { name: string; severity_code: number };
      isHeadRow?: boolean;
    };
    const statusName = typedRow.status.name;

    const isNestedRow =
      equals(displayType, DisplayType.Host) && isNil(typedRow?.isHeadRow);

    if (isNestedRow) {
      return <div />;
    }

    const label = equals(SeverityCode[5], statusName)
      ? t(statusName)
      : t(statusName);

    return (
      <div className={classes.statusColumn}>
        {isHovered && !isOnPublicPage ? (
          <StatusColumnOnHover row={row} />
        ) : (
          <StatusChip
            className={classes.statusColumnChip}
            label={label}
            severityCode={typedRow.status.severity_code}
          />
        )}
      </div>
    );
  };
};

export default StatusColumn;
