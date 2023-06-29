import { atom, useAtom } from 'jotai';
import { dec, equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { CircularProgress } from '@mui/material';

import { useInfiniteScrollListing } from '@centreon/ui';
import { List, Modal } from '@centreon/ui/components';

import { dashboardAccessRightsListDecoder } from '../api/decoders';
import { getDashboardAccessRightsEndpoint } from '../api/endpoints';
import { labelSave } from '../Dashboard/translatedLabels';
import { labelCancel } from '../translatedLabels';
import { NamedEntity } from '../api/models';

import { selectedDashboardShareAtom } from './atoms';
import useShareForm from './useShareForm';
import UserRoleItem from './UserRoleItem';
import { labelUserRoles } from './translatedLabels';

export const pageAtom = atom(1);

interface Props {
  id?: NamedEntity['id'];
}

const SharesList = ({ id }: Props): JSX.Element => {
  const { t } = useTranslation();

  const [selectedDashboardShare, setSelectedDashboardShare] = useAtom(
    selectedDashboardShareAtom
  );

  const {
    elements: dashboardShares,
    isLoading,
    elementRef
  } = useInfiniteScrollListing({
    decoder: dashboardAccessRightsListDecoder,
    endpoint: getDashboardAccessRightsEndpoint(selectedDashboardShare),
    pageAtom,
    queryKeyName: 'dashboard_shares'
  });

  const closeModal = (): void => setSelectedDashboardShare(undefined);

  const {
    values,
    handleChange,
    getInputName,
    toggleContact,
    dirty,
    submitForm
  } = useShareForm({
    dashboardId: id,
    shares: dashboardShares
  });

  return (
    <>
      <Modal.Body>
        <strong>{t(labelUserRoles)}</strong>
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
                isRemoved={dashboardShare.isRemoved}
                key={dashboardShare.id}
                role={dashboardShare.role}
                toggle={toggleContact({
                  index,
                  value: dashboardShare.isRemoved
                })}
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
        onConfirm={submitForm}
      />
    </>
  );
};

export default SharesList;
