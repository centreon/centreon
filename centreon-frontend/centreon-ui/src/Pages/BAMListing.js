/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import Paper from '@material-ui/core/Paper';
import {
  CustomRow,
  CustomColumn,
  Breadcrumb,
  Divider,
  InputFieldSearch,
  ButtonCustom,
  TableCustom,
  IconDelete,
  IconLibraryAdd,
  IconPowerSettings,
  IconPowerSettingsDisable,
  IconInsertChart,
  Tooltip,
  MassiveChangeDialog,
  ConfirmationDialog,
  PromptDialog,
} from '../index';

import TABLE_COLUMN_TYPES from '../Table/ColumnTypes';

const breadcrumbs = [
  {
    label: 'Configuration',
    link: './main.php?p=6',
  },
  {
    label: 'Business Activity',
    link: './main.php?p=626',
  },
  {
    label: 'Activities',
    link: '',
  },
];

const tableConfiguration = [
  {
    id: 'name',
    numeric: false,
    disablePadding: true,
    label: 'Name',
    type: TABLE_COLUMN_TYPES.string,
  },
  {
    id: '#',
    numeric: true,
    disablePadding: false,
    label: '',
    type: TABLE_COLUMN_TYPES.hoverActions,
  },
  {
    id: 'activate',
    numeric: true,
    disablePadding: false,
    label: 'Activate',
    type: TABLE_COLUMN_TYPES.toggler,
  },
  {
    id: 'level_w & level_c',
    numeric: true,
    disablePadding: false,
    columns: [
      {
        id: 'level_w',
        label: 'Warning:',
        type: 'percentage',
      },
      {
        id: 'level_c',
        label: 'Critical:',
        type: 'percentage',
      },
    ],
    label: 'Calculation method',
    type: TABLE_COLUMN_TYPES.multicolumn,
  },
  {
    id: 'description',
    numeric: true,
    disablePadding: false,
    label: 'Description',
    type: TABLE_COLUMN_TYPES.number,
  },
];

class BAMListingPage extends Component {
  state = {
    massiveChangeActive: false,
    deleteActive: false,
    duplicateActive: false,
  };

  toggleDeleteModal = (selected) => {
    const { deleteActive } = this.state;
    const { onTableSelectionChanged } = this.props;
    this.setState({
      deleteActive: !deleteActive,
    });
    if (selected[0]) {
      onTableSelectionChanged(selected);
    }
  };

  toggleMassiveChangeModal = () => {
    const { massiveChangeActive } = this.state;
    this.setState({
      massiveChangeActive: !massiveChangeActive,
    });
  };

  toggleDuplicateModal = (selected) => {
    const { duplicateActive } = this.state;
    const { onTableSelectionChanged } = this.props;
    this.setState({
      duplicateActive: !duplicateActive,
    });
    if (selected[0]) {
      onTableSelectionChanged(selected);
    }
  };

  onTableSelection = (selected) => {
    const { onTableSelectionChanged } = this.props;
    onTableSelectionChanged(selected);
  };

  render() {
    const {
      onAddClicked,
      onSearch,
      onDelete,
      onDuplicate,
      onMassiveChange,
      onEnable,
      onDisable,
      onPaginate,
      onSort,
      tableData,
      onPaginationLimitChanged,
      paginationLimit,
      totalRows,
      currentPage,
      currentlySelected,
    } = this.props;
    const { massiveChangeActive, deleteActive, duplicateActive } = this.state;
    return (
      <React.Fragment>
        <Breadcrumb breadcrumbs={breadcrumbs} />
        <Divider />
        <Paper elevation={0} style={{ padding: '8px 16px' }}>
          <CustomRow>
            <CustomColumn
              customColumn="md-4"
              additionalStyles={[
                'flex-none',
                'container__col-xs-12',
                'm-0',
                'mr-2',
              ]}
            >
              <InputFieldSearch onChange={onSearch} />
            </CustomColumn>
          </CustomRow>
        </Paper>
        <Divider />
        <Paper elevation={0} style={{ padding: '8px 16px' }}>
          <CustomRow>
            <CustomColumn
              customColumn="md-4"
              additionalStyles={['flex-none', 'container__col-xs-12', 'm-0']}
            >
              <ButtonCustom label="ADD" onClick={onAddClicked} />
            </CustomColumn>
            {currentlySelected.length > 0 ? (
              <React.Fragment>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    'flex-none',
                    'container__col-xs-12',
                    'm-0',
                    'pr-09',
                  ]}
                >
                  <Tooltip label="Delete">
                    <IconDelete
                      label="Delete"
                      onClick={this.toggleDeleteModal}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    'flex-none',
                    'container__col-xs-12',
                    'm-0',
                    'pr-10',
                    'pl-05',
                  ]}
                >
                  <Tooltip label="Duplicate">
                    <IconLibraryAdd
                      label="Duplicate"
                      onClick={this.toggleDuplicateModal}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    'flex-none',
                    'container__col-xs-12',
                    'm-0',
                    'pr-10',
                    'pl-05',
                  ]}
                >
                  <Tooltip label="Enable">
                    <IconPowerSettings
                      active
                      label="Enable"
                      onClick={onEnable}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    'flex-none',
                    'container__col-xs-12',
                    'm-0',
                    'pl-05',
                    'pr-24',
                    'border-right',
                  ]}
                >
                  <Tooltip label="Disable">
                    <IconPowerSettingsDisable
                      active
                      label="Disable"
                      onClick={onDisable}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    'flex-none',
                    'container__col-xs-12',
                    'm-0',
                    'pl-22',
                  ]}
                >
                  <Tooltip label="Massive change">
                    <IconInsertChart
                      label="Massive change"
                      onClick={this.toggleMassiveChangeModal}
                    />
                  </Tooltip>
                </CustomColumn>
              </React.Fragment>
            ) : null}
          </CustomRow>
        </Paper>
        <Paper elevation={0} style={{ padding: '8px 16px', paddingTop: 0 }}>
          <TableCustom
            columnConfiguration={tableConfiguration}
            tableData={tableData}
            onTableSelectionChanged={this.onTableSelection}
            onDelete={this.toggleDeleteModal}
            onPaginate={onPaginate}
            onSort={onSort}
            onDuplicate={this.toggleDuplicateModal}
            onPaginationLimitChanged={onPaginationLimitChanged}
            limit={paginationLimit}
            currentPage={currentPage}
            totalRows={totalRows}
            checkable
            onEnable={onEnable}
            onDisable={onDisable}
            selected={currentlySelected}
          />
        </Paper>
        <MassiveChangeDialog
          header="Massive calculation method change"
          info="Input value of critical and warning threshold for selected BAs"
          active={massiveChangeActive}
          onNoClicked={this.toggleMassiveChangeModal}
          onClose={this.toggleMassiveChangeModal}
          onYesClicked={(thresholds) => {
            this.setState(
              {
                massiveChangeActive: false,
              },
              () => {
                onMassiveChange(thresholds);
              },
            );
          }}
        />
        <ConfirmationDialog
          active={deleteActive}
          info="Delete selected business activities?"
          onNoClicked={this.toggleDeleteModal}
          onClose={this.toggleDeleteModal}
          onYesClicked={() => {
            this.setState(
              {
                deleteActive: false,
              },
              onDelete,
            );
          }}
        />
        <PromptDialog
          customStyle={{ padding: '25px 20px' }}
          info="How many times would you like to duplicate selected BAs?"
          active={duplicateActive}
          onNoClicked={this.toggleDuplicateModal}
          onClose={this.toggleDuplicateModal}
          onYesClicked={(times) => {
            this.setState(
              {
                duplicateActive: false,
              },
              () => {
                onDuplicate(times);
              },
            );
          }}
        />
      </React.Fragment>
    );
  }
}

export default BAMListingPage;
