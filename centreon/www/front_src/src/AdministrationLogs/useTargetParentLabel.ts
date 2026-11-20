import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { Target } from './models';
import {
  labelLinkedResourceChanged,
  labelOnHost,
  labelOnHostGroup
} from './translatedLabels';

// Where a service lives ("on host-1"), since its name alone is ambiguous. Null
// for the other types
export const useTargetParentLabel = (): ((
  target: Pick<Target, 'parent' | 'type'>
) => string | null) => {
  const { t } = useTranslation();

  return ({ parent, type }) => {
    if (!equals(type, 'Service')) {
      return null;
    }

    if (!parent || isEmpty(parent.names)) {
      return t(labelLinkedResourceChanged);
    }

    return t(
      equals(parent.type, 'HostGroup') ? labelOnHostGroup : labelOnHost,
      { names: parent.names.join(', ') }
    );
  };
};
