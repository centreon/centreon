import { useAtomValue } from 'jotai';
import {
  always,
  any,
  equals,
  find,
  head,
  ifElse,
  isEmpty,
  isNil,
  map,
  partition,
  pathEq,
  pipe,
  propEq,
  reject
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { aclAtom } from '@centreon/ui-context';

import { Resource, ResourceCategory } from '../models';
import { labelHostsDenied, labelServicesDenied } from '../translatedLabels';

interface AclQuery {
  canAcknowledge: (resources) => boolean;
  canAcknowledgeServices: () => boolean;
  canCheck: (resources) => boolean;
  canDisacknowledgeServices: () => boolean;
  canDowntime: (resources) => boolean;
  canDowntimeServices: () => boolean;
  canForcedCheck: (resource) => boolean;
  getAcknowledgementDeniedTypeAlert: (resources) => string | undefined;
  getDowntimeDeniedTypeAlert: (resources) => string | undefined;
}

const useAclQuery = (): AclQuery => {
  const { t } = useTranslation();
  const acl = useAtomValue(aclAtom);

  const toType = ({ type }): string => ResourceCategory[type];

  const can = ({
    resources,
    action
  }: {
    action: string;
    resources: Array<Resource>;
  }): boolean => {
    return pipe(
      map(toType),
      any((type) => pathEq(true, ['actions', type, action])(acl))
    )(resources);
  };

  const cannot =
    (action) =>
    (resources): boolean =>
      !can({ action, resources });

  const getDeniedTypeAlert = ({ resources, action }): string | undefined => {
    const isHost = propEq('host', 'type');

    return pipe(
      partition(isHost),
      reject(isEmpty),
      find(cannot(action)),
      ifElse(
        isNil,
        always(undefined),
        pipe(
          head,
          toType,
          ifElse(
            equals('host'),
            always(t(labelHostsDenied)),
            always(t(labelServicesDenied))
          )
        )
      )
    )(resources);
  };

  const canDowntime = (resources: Array<Resource>): boolean => {
    return can({ action: 'downtime', resources });
  };

  const getDowntimeDeniedTypeAlert = (
    resources: Array<Resource>
  ): string | undefined => {
    return getDeniedTypeAlert({ action: 'downtime', resources });
  };

  const canDowntimeServices = (): boolean =>
    pathEq(true, ['actions', 'service', 'downtime'])(acl);

  const canAcknowledge = (resources: Array<Resource>): boolean => {
    return can({ action: 'acknowledgement', resources });
  };

  const getAcknowledgementDeniedTypeAlert = (
    resources: Array<Resource>
  ): string | undefined => {
    return getDeniedTypeAlert({ action: 'acknowledgement', resources });
  };

  const canAcknowledgeServices = (): boolean =>
    pathEq(true, ['actions', 'service', 'acknowledgement'])(acl);

  const canCheck = (resources: Array<Resource>): boolean => {
    return can({ action: 'check', resources });
  };
  const canForcedCheck = (resources: Array<Resource>): boolean => {
    return can({ action: 'forced_check', resources });
  };

  const canDisacknowledgeServices = (): boolean =>
    pathEq(true, ['actions', 'service', 'disacknowledgement'])(acl);

  return {
    canAcknowledge,
    canAcknowledgeServices,
    canCheck,
    canDisacknowledgeServices,
    canDowntime,
    canDowntimeServices,
    canForcedCheck,
    getAcknowledgementDeniedTypeAlert,
    getDowntimeDeniedTypeAlert
  };
};

export default useAclQuery;
