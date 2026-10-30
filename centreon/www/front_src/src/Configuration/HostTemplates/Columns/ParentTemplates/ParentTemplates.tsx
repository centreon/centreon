import { Link, Tooltip } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { isEmpty } from 'ramda';
import type { JSX } from 'react';
import { Link as RouterLink } from 'react-router';

import type { NamedEntity } from '../../models';
import { getHostTemplateSearch } from '../../utils';

const ParentTemplates = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const parentTemplates = (row.parentTemplates ?? []) as Array<NamedEntity>;

  if (isEmpty(parentTemplates)) {
    return null;
  }

  const names = parentTemplates.map(({ name }) => name).join(', ');

  return (
    // The cell clips, so the tooltip is the only way to read a long list.
    <Tooltip title={names}>
      <div className="flex items-center gap-1 overflow-hidden">
        {parentTemplates.map(({ id, name }, index) => (
          <span key={id}>
            <Link
              component={RouterLink}
              // Pendo keys off this, so it must not follow the labels.
              data-testid={`host-template-link_${id}`}
              // Same page: the search alone opens the parent through the deep
              // link `ConfigurationBase` already follows.
              to={{ search: getHostTemplateSearch(id) }}
              underline="hover"
              variant="body2"
            >
              {name}
            </Link>
            {index < parentTemplates.length - 1 && ' | '}
          </span>
        ))}
      </div>
    </Tooltip>
  );
};

export default ParentTemplates;
