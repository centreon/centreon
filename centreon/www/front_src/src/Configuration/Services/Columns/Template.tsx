import { Link } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import type { JSX } from 'react';

import type { NamedEntity } from '../models';
import { getServiceTemplateConfigurationUrl } from '../utils';

const Template = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const template = row.template as NamedEntity | null | undefined;

  if (!template) {
    return null;
  }

  return (
    <Link
      // Pendo keys off this, so it must not follow the labels.
      data-testid={`service-template-link_${template.id}`}
      href={getServiceTemplateConfigurationUrl(template.id)}
      underline="hover"
      variant="body2"
    >
      {template.name}
    </Link>
  );
};

export default Template;
