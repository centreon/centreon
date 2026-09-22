import { Link, Tooltip } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { isEmpty } from 'ramda';
import type { JSX } from 'react';

import type { NamedEntity } from '../../models';
import { getHostTemplateConfigurationUrl } from '../../utils';

const Templates = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const templates = (row.templates ?? []) as Array<NamedEntity>;

  if (isEmpty(templates)) {
    return null;
  }

  const names = templates.map(({ name }) => name).join(', ');

  return (
    // The cell clips its content, so the tooltip is the only way to read a
    // list that does not fit.
    <Tooltip title={names}>
      <div className="flex items-center gap-1 overflow-hidden">
        {templates.map(({ id, name }, index) => (
          <span key={id}>
            <Link
              // Decoupled from the translated labels on purpose: Pendo keys off
              // this attribute and the wording is still moving.
              data-testid={`host-template-link_${id}`}
              href={getHostTemplateConfigurationUrl(id)}
              underline="hover"
              variant="body2"
            >
              {name}
            </Link>
            {index < templates.length - 1 && ','}
          </span>
        ))}
      </div>
    </Tooltip>
  );
};

export default Templates;
