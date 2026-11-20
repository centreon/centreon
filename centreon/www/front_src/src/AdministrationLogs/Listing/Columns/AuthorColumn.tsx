import type { ComponentColumnProps } from '@centreon/ui';

import Author from '../../Author';
import type { ActivityLog } from '../../models';
import { useAddFilter } from '../../useAddFilter';
import FilterableValue from './FilterableValue';

const AuthorColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const addFilter = useAddFilter();

  const { actor } = row as unknown as ActivityLog;

  // Only a named user can be filtered on
  if (!actor?.name) {
    return <Author actor={actor} />;
  }

  const { id, name } = actor;

  return (
    <FilterableValue
      label={name}
      onFilter={() => addFilter('actors', { id, name })}
    >
      <Author actor={actor} />
    </FilterableValue>
  );
};

export default AuthorColumn;
