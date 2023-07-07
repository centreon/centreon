import { ReactElement, useMemo } from 'react';

import { useAccessRightsForm } from '../useAccessRightsForm';
import { ContactAccessRightState } from '../AccessRights.resource';

import { useStyles } from './AccessRightsStats.styles';

export type AccessRightsStatsProps = {
  labels: Record<Exclude<ContactAccessRightState, 'unchanged'>, string>;
};

const AccessRightsStats = ({
  labels
}: AccessRightsStatsProps): ReactElement => {
  const { classes } = useStyles();

  const { stats } = useAccessRightsForm();

  const statsElements = useMemo(() => {
    const elements: Array<ReactElement> = [];

    ['added', 'updated', 'removed'].forEach((state) => {
      if (stats?.[state] > 0)
        elements.push(
          <span>
            <strong>{stats[state]}</strong> {labels[state]}
          </span>
        );
    });

    return elements;
  }, [stats]);

  return (
    <div className={classes.accessRightsStats}>
      {statsElements.length !== 0 && statsElements.map((el) => el)}
    </div>
  );
};

export { AccessRightsStats };
