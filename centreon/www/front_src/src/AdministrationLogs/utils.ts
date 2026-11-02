import { SeverityCode } from '@centreon/ui';

import { Action } from './models';
import {
  labelAdded,
  labelChanged,
  labelDeleted,
  labelDisabled,
  labelEnabled,
  labelMassChange
} from './translatedLabels';

export const actionLabels: Record<Action, string> = {
  [Action.Add]: labelAdded,
  [Action.Delete]: labelDeleted,
  [Action.Disable]: labelDisabled,
  [Action.Enable]: labelEnabled,
  [Action.MassChange]: labelMassChange,
  [Action.Update]: labelChanged
};

export const actionSeverityCodes: Record<Action, SeverityCode> = {
  [Action.Add]: SeverityCode.OK,
  [Action.Delete]: SeverityCode.High,
  [Action.Disable]: SeverityCode.High,
  [Action.Enable]: SeverityCode.OK,
  [Action.MassChange]: SeverityCode.Medium,
  [Action.Update]: SeverityCode.Medium
};

// Configuration pages an object can be opened in, for the types that have one
export const getConfigurationPath = ({
  id,
  type
}: {
  id: number;
  type: string;
}): string | null => {
  const paths: Record<string, string> = {
    Host: `/main.php?p=60101&o=c&host_id=${id}`,
    Service: `/main.php?p=60201&o=c&service_id=${id}`
  };

  return paths[type] ?? null;
};
