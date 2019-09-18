/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import Paper from '@material-ui/core/Paper';
import {
  CustomRow,
  CustomColumn,
  Breadcrumb,
  InputFieldSearch,
  ButtonCustom,
  TableCustom,
  IconInsertChart,
  MassiveChangeDialog,
  ConfirmationDialog,
  PromptDialog,
} from '../index';
import IconDelete from '../MaterialComponents/Icons/IconDelete';
import IconLibraryAdd from '../MaterialComponents/Icons/IconLibraryAdd';
import IconPowerSettings from '../MaterialComponents/Icons/IconPowerSettings';
import IconPowerSettingsDisable from '../MaterialComponents/Icons/IconPowerSettingsDisable';
import Tooltip from '../MaterialComponents/Tooltip';

import Grid from '@material-ui/core/Grid';

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
    link: './configuration/bam/bas',
  },
];

const tableConfiguration = [
  {
    id: 'name',
    numeric: false,
    disablePadding: true,
    label: 'Name',
    type: TABLE_COLUMN_TYPES.string,
    image: true,
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
    label: 'State',
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
      onRowClick,
    } = this.props;
    const { massiveChangeActive, deleteActive, duplicateActive } = this.state;
    return (
      <div aria-label="Business Activity Page">
        <Paper
          elevation={0}
          style={{
            padding: '16px 0',
            marginLeft: '16px',
            marginRight: '16px',
            borderRadius: '0',
            borderBottom: '1px solid #e4e4e4',
            borderTop: '1px solid #e4e4e4',
          }}
        >
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
              <InputFieldSearch onChange={onSearch} id="searchBA" />
            </CustomColumn>
          </CustomRow>
        </Paper>
        <Paper elevation={0} style={{ padding: '16px 16px 8px 16px' }}>
          <Grid item={true}>
            <Grid container={true} spacing={3}>
              <Grid item={true}>
                <ButtonCustom label={"ADD"} onClick={onAddClicked} aria-label="ADD" style={{ marginBottom: '8px' }} />
              </Grid>
              {currentlySelected.length > 0 ? (
                <Grid item={true}>
                  <Grid container={true} spacing={2}>
                    <Grid item={true}>
                      <Tooltip label={"Duplicate"} onClick={this.toggleDuplicateModal}>
                        <IconLibraryAdd />
                      </Tooltip>
                    </Grid>
                    <Grid item={true}>
                      <Tooltip label={"Delete"} onClick={this.toggleDeleteModal}>
                        <IconDelete />
                      </Tooltip>
                    </Grid>
                    <Grid item={true}>
                      <Tooltip label={"Disable"} onClick={onDisable}>
                        <IconPowerSettingsDisable />
                      </Tooltip>
                    </Grid>
                    <Grid item={true} style={{ borderRight: '2px solid #dcdcdc' }}>
                      <Tooltip label={"Enable"} onClick={onEnable}>
                        <IconPowerSettings />
                      </Tooltip>
                    </Grid>
                    <Grid item={true}>
                      <Tooltip label="Massive change">
                        <IconInsertChart
                          label="Massive change"
                          onClick={this.toggleMassiveChangeModal}
                        />
                      </Tooltip>
                    </Grid>
                  </Grid>
                </Grid>
              ) : null}
            </Grid>
          </Grid>
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
            enabledColumn="activate"
            onRowClick={onRowClick}
            emptyDataMessage="No Business Activity found."
            ariaLabel="Business Activity Table"
          />
        </Paper>
        <MassiveChangeDialog
          customStyle={{ padding: '25px 20px' }}
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
      </div>
    );
  }
}

export default BAMListingPage;
