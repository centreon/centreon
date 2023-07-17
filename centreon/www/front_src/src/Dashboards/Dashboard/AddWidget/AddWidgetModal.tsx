import { ChangeEvent } from 'react';

import { useTranslation } from 'react-i18next';
import { isEmpty } from 'ramda';

import { DataTable, Modal } from '@centreon/ui/components';
import { SearchField } from '@centreon/ui';

import {
  labelNoWidgetFound,
  labelSearch,
  labelSelectAWidgetType
} from '../translatedLabels';

import useAddWidget from './useAddWidget';
import useSearchWidgets from './useSearchWidgets';
import { useAddWidgetStyles } from './addWidget.styles';

const AddWidgetModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useAddWidgetStyles();

  const { isAddWidgetModalOpened, closeModal, addWidget } = useAddWidget();

  const { search, setSearch, filteredWidgets } = useSearchWidgets();

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setSearch(event.target.value);
  };

  const isEmptyList = isEmpty(filteredWidgets);

  return (
    <Modal open={isAddWidgetModalOpened} size="large" onClose={closeModal}>
      <Modal.Header>{t(labelSelectAWidgetType)}</Modal.Header>
      <Modal.Body>
        <SearchField
          className={classes.search}
          dataTestId="search widget"
          placeholder={t(labelSearch) as string}
          value={search}
          onChange={change}
        />
        <div>
          <DataTable isEmpty={isEmptyList}>
            {isEmptyList && (
              <DataTable.EmptyState
                aria-label={t(labelNoWidgetFound)}
                canCreate={false}
                data-testid={labelNoWidgetFound}
                labels={{
                  title: t(labelNoWidgetFound)
                }}
              />
            )}
            {filteredWidgets.map(
              ({ moduleName, federatedComponentsConfiguration }) => (
                <DataTable.Item
                  hasCardAction
                  key={moduleName}
                  title={federatedComponentsConfiguration.title || moduleName}
                  onClick={() => addWidget(federatedComponentsConfiguration)}
                />
              )
            )}
          </DataTable>
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default AddWidgetModal;
