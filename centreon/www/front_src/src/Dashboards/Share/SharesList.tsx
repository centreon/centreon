import { atom, useAtom } from 'jotai';
import { equals, dec, head } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, CircularProgress } from '@mui/material';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';

import { SelectField, useInfiniteScrollListing } from '@centreon/ui';
import { IconButton, List, Modal } from '@centreon/ui/components';

import { dashboardShareListDecoder } from '../api/decoders';
import { getDashboardSharesEndpoint } from '../api/endpoints';
import { selectedDashboardShareAtom } from '../atoms';
import { DashboardRole } from '../models';
import { labelSave } from '../Dashboard/translatedLabels';
import { labelCancel } from '../translatedLabels';

import useShareForm from './useShareForm';

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
              <List.Item
                action={
                  <Box sx={{ columnGap: 2, display: 'flex' }}>
                    <SelectField
                      options={[
                        {
                          id: DashboardRole.editor,
                          name: DashboardRole.editor
                        },
                        {
                          id: DashboardRole.viewer,
                          name: DashboardRole.viewer
                        }
                      ]}
                      selectedOptionId={dashboardShare.role}
                      sx={{ width: 85 }}
                      onChange={handleChange(getInputName(index))}
                    />
                    <IconButton
                      icon={<DeleteOutlineIcon />}
                      onClick={removeContact(dashboardShare.id)}
                    />
                  </Box>
                }
                key={dashboardShare.id}
                ref={isLastElement ? elementRef : undefined}
              >
                <List.Avatar>{head(dashboardShare.fullname)}</List.Avatar>
                <List.ItemText
                  primaryText={dashboardShare.fullname}
                  secondaryText={dashboardShare.email}
                />
              </List.Item>
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
