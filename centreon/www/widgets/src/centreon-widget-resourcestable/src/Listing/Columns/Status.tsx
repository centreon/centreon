import { useAtomValue, useSetAtom } from 'jotai';
import { path, equals, isNil, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';

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

import { isOnPublicPageAtom } from '@centreon/ui-context';
import IconDowntime from './Icons/Downtime';
import { useStyles } from './Status.styles';

const StatusColumnOnHover = ({
  row
}: Pick<ComponentColumnProps, 'row'>): JSX.Element => {
  const { dataStyle } = useStyleTable({});
  const { classes } = useStyles({ data: dataStyle.statusColumnChip });
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const setResourcesToAcknowledge = useSetAtom(resourcesToAcknowledgeAtom);
  const setResourcesToSetDowntime = useSetAtom(resourcesToSetDowntimeAtom);

  const forcedCheckEndpoint = path(['links', 'endpoints', 'forced_check'], row);

  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => forcedCheckEndpoint,
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
    setResourcesToAcknowledge([row]);
  };

  const setDowntime = (): void => {
    setResourcesToSetDowntime([row]);
  };

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
        onClick={acknowledge}
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
        onClick={setDowntime}
      >
        <IconDowntime fontSize="small" />
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
        onClick={forcedCheck}
      >
        <IconForcedCheck fontSize="small" />
      </IconButton>
    </div>
  );
};

const StatusColumn =
  ({ displayType, classes, t }) =>
  ({ row, isHovered }: ComponentColumnProps): JSX.Element => {
    const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

    const statusName = row.status.name;

    const isNestedRow =
      equals(displayType, DisplayType.Host) && isNil(row?.isHeadRow);

    if (isNestedRow) {
      return <div />;
    }

    const label = equals(SeverityCode[5], statusName) ? (
      <>{t(statusName)}</>
    ) : (
      t(statusName)
    );

    return (
      <div className={classes.statusColumn}>
        {isHovered && !isOnPublicPage ? (
          <StatusColumnOnHover row={row} />
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

export default StatusColumn;
