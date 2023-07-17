import { ReactElement } from 'react';

import { List } from '../../../List';

import { useStyles } from './ContactAccessRightsList.styles';
import { ContactAccessRightsListItemSkeleton } from './ContactAccessRightsListItemSkeleton';

export type ContactAccessRightsListSkeletonProps = {
  length?: number;
};

const ContactAccessRightsListSkeleton = ({
  length = 3
}: ContactAccessRightsListSkeletonProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.contactAccessRightsList}>
      <List>
        {[...Array(length).keys()].map((key) => (
          <ContactAccessRightsListItemSkeleton key={key} />
        ))}
      </List>
    </div>
  );
};

export { ContactAccessRightsListSkeleton };
