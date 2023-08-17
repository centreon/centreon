import { ReactElement } from 'react';

import { List } from '../../../List';

import { useStyles } from './ContactAccessRightsList.styles';

export type ContactAccessRightsListItemSkeletonProps = {
  hasAvatar?: boolean;
};

const ContactAccessRightsListItemSkeleton = ({
  hasAvatar = true
}: ContactAccessRightsListItemSkeletonProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <List.Item className={classes.contactAccessRightsListItem}>
      <span>
        {hasAvatar && <List.Item.Avatar.Skeleton />}
        <List.Item.Text.Skeleton secondaryText />
      </span>
    </List.Item>
  );
};

export { ContactAccessRightsListItemSkeleton };
