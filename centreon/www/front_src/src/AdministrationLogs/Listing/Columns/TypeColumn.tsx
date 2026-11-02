import { Typography } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import type { ActivityLog } from '../../models';
import { labelTargetTypes } from '../../translatedLabels';
import { useAddFilter } from '../../useAddFilter';
import FilterableValue from './FilterableValue';

const TypeColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const addFilter = useAddFilter();

  const { type } = (row as unknown as ActivityLog).target;
  const label = t(labelTargetTypes[type] ?? type);

  return (
    <FilterableValue
      label={label}
      onFilter={() => addFilter('targetTypes', { id: type, name: label })}
    >
      <Typography
        className="truncate text-text-secondary"
        component="span"
        variant="body2"
      >
        {label}
      </Typography>
    </FilterableValue>
  );
};

export default TypeColumn;
