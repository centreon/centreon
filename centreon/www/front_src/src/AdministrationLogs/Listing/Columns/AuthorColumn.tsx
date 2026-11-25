import type { ComponentColumnProps } from '@centreon/ui';

import Author from '../../Author';
import type { ActivityLog } from '../../models';
import TokenName from '../../TokenName';
import { useAddFilter } from '../../useAddFilter';
import FilterableValue from './FilterableValue';

const AuthorColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const addFilter = useAddFilter();

  const { actor, tokenName } = row as unknown as ActivityLog;

  return (
    <div className="flex min-w-0 items-baseline gap-2">
      {/* Only a named user can be filtered on */}
      {actor?.name ? (
        <FilterableValue
          label={actor.name}
          onFilter={() =>
            addFilter('actors', { id: actor.id, name: actor.name as string })
          }
        >
          <Author actor={actor} />
        </FilterableValue>
      ) : (
        <Author actor={actor} />
      )}
      {tokenName && (
        <FilterableValue
          label={tokenName}
          onFilter={() =>
            addFilter('tokens', { id: tokenName, name: tokenName })
          }
        >
          <TokenName name={tokenName} />
        </FilterableValue>
      )}
    </div>
  );
};

export default AuthorColumn;
