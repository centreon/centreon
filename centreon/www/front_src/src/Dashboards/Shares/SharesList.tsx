import { atom, useAtom } from 'jotai';
import { equals, dec } from 'ramda';
import { useTranslation } from 'react-i18next';

import { CircularProgress } from '@mui/material';

import { useInfiniteScrollListing } from '@centreon/ui';
import { List, Modal } from '@centreon/ui/components';

import { dashboardShareListDecoder } from '../api/decoders';
import { getDashboardSharesEndpoint } from '../api/endpoints';
import { selectedDashboardShareAtom } from '../atoms';
import { labelSave } from '../Dashboard/translatedLabels';
import { labelCancel } from '../translatedLabels';

import useShareForm from './useShareForm';
import UserRoleItem from './UserRoleItem';

const pageAtom = atom(1);

const SharesList = (): JSX.Element => {
  const { t } = useTranslation();

  const [selectedDashboardShare, setSelectedDashboardShare] = useAtom(
    selectedDashboardShareAtom
  );

  const {
    elements: dashboardShares,
    isLoading,
    elementRef
  } = useInfiniteScrollListing({
    decoder: dashboardShareListDecoder,
    endpoint: getDashboardSharesEndpoint(selectedDashboardShare),
    pageAtom,
    queryKeyName: 'dashboard_shares'
  });

  const closeModal = (): void => setSelectedDashboardShare(undefined);

  const { values, handleChange, getInputName, removeContact, dirty } =
    useShareForm({
      shares: dashboardShares
    });

  return (
    <>
      <Modal.Body>
        <List>
          {values.map((dashboardShare, index) => {
            const isLastElement = equals(index, dec(dashboardShares.length));

            return (
              <UserRoleItem
                change={handleChange(getInputName(index))}
                elementRef={isLastElement ? elementRef : undefined}
                email={dashboardShare.email}
                fullname={dashboardShare.fullname}
                id={dashboardShare.id}
                key={dashboardShare.id}
                remove={removeContact(dashboardShare.id)}
                role={dashboardShare.role}
              />
            );
          })}
          {isLoading && <CircularProgress />}
        </List>
      </Modal.Body>
      <Modal.Actions
        disabled={!dirty}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelSave)
        }}
        onCancel={closeModal}
      />
    </>
  );
};

export default SharesList;
