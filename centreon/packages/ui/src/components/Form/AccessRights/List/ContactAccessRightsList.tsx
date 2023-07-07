import { ReactElement } from 'react';

import { List } from '../../../List';
import { useAccessRightsForm } from '../useAccessRightsForm';

import {
  ContactAccessRightsListItem,
  ContactAccessRightsListItemProps
} from './ContactAccessRightsListItem';
import { useStyles } from './ContactAccessRightsList.styles';

export type ContactAccessRightsListProps = {
  labels: ContactAccessRightsListLabels;
};

type ContactAccessRightsListLabels = {
  emptyState: string;
  item: ContactAccessRightsListItemProps['labels'];
};

const ContactAccessRightsList = ({
  labels
}: ContactAccessRightsListProps): ReactElement => {
  const { classes } = useStyles();
  const { contactAccessRights } = useAccessRightsForm();

  return (
    <div className={classes.contactAccessRightsList}>
      {contactAccessRights.length === 0 ? (
        <div className={classes.contactAccessRightsListEmpty}>
          {labels.emptyState}
        </div>
      ) : (
        <List>
          {contactAccessRights.map((resource) => (
            <ContactAccessRightsListItem
              key={resource.contactAccessRight.contact?.id}
              labels={labels.item}
              resource={resource}
            />
          ))}
        </List>
      )}
    </div>
  );
};

export { ContactAccessRightsList };
