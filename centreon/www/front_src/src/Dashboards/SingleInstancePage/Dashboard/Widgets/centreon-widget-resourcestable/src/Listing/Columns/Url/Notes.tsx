import IconLink from '@mui/icons-material/Link';

import type { ComponentColumnProps } from '@centreon/ui';

import { path } from 'ramda';

import UrlColumn from '.';

const NotesUrlColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const endpoint = path<string | undefined>(
    ['links', 'externals', 'notes', 'url'],
    row
  );

  const title = path<string | undefined>(
    ['links', 'externals', 'notes', 'label'],
    row
  );

  return (
    <UrlColumn
      avatarTitle="N"
      endpoint={endpoint}
      icon={<IconLink fontSize="small" />}
      title={title}
    />
  );
};

export default NotesUrlColumn;
