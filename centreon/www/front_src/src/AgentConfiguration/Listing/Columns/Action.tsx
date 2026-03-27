import { CodeOffTwoTone, DeleteOutline } from '@mui/icons-material';

import { IconButton } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil, pick } from 'ramda';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { itemToDeleteAtom, pollerToGenerateCommanAtom } from '../../atoms';
import { AgentConfigurationListing, AgentType } from '../../models';
import { labelCommand, labelDelete } from '../../translatedLabels';
import { useStyles } from './Action.styles';

interface Props {
  row: AgentConfigurationListing & {
    internalListingParentId?: number;
    internalListingParentRow: AgentConfigurationListing;
  };
}

const Action = ({ row }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { isAdmin } = useAtomValue(userAtom);
  const { isCloudPlatform } = useAtomValue(platformFeaturesAtom);
  const hasCentral = (
    isNotNil(row.internalListingParentId)
      ? row.internalListingParentRow?.pollers
      : row?.pollers
  )?.some((poller) => equals(poller?.isCentral, true));

  const setItemToDelete = useSetAtom(itemToDeleteAtom);
  const setOpenFormModal = useSetAtom(pollerToGenerateCommanAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      agent: isNotNil(row.internalListingParentId)
        ? pick(['id', 'name'], row.internalListingParentRow)
        : pick(['id', 'name'], row),
      poller: isNotNil(row.internalListingParentId)
        ? pick(['id', 'name'], row)
        : undefined
    });
  };

  const displayCommandModal = useCallback(
    () => setOpenFormModal(pick(['id', 'name'], row)),
    []
  );

  const isDeleteButtonDisplayed = isAdmin || !isCloudPlatform || !hasCentral;
  const isCommandButtonDisplayed =
    isNotNil(row.internalListingParentId) &&
    equals(row.internalListingParentRow.type, AgentType.CMA) &&
    !!row.internalListingParentRow?.isAgentInitiated;

  return (
    <div className="grid grid-cols-2 grid-3">
      <div>
        {isCommandButtonDisplayed && (
          <IconButton
            ariaLabel={t(labelCommand)}
            onClick={displayCommandModal}
            title={t(labelCommand)}
          >
            <CodeOffTwoTone className="text-xl" />
          </IconButton>
        )}
      </div>
      <div>
        {isDeleteButtonDisplayed && (
          <IconButton
            ariaLabel={t(labelDelete)}
            className={classes.removeButton}
            onClick={askBeforeDelete}
            title={t(labelDelete)}
          >
            <DeleteOutline className="text-x" />
          </IconButton>
        )}
      </div>
    </div>
  );
};

export default Action;
